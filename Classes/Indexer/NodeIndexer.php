<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Domain\Model\TargetContextPath;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\DocumentDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\IndexDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\IndexerDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\NodeTypeMappingBuilderInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\RequestDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\SystemDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ErrorHandling\ErrorHandlingService;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ErrorHandling\ErrorStorageInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\DimensionsService;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\IndexNameService;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\NodeTypeIndexingConfiguration;
use Flowpack\ElasticSearch\Domain\Model\Document as ElasticSearchDocument;
use Flowpack\ElasticSearch\Domain\Model\Index;
use Flowpack\ElasticSearch\Transfer\Exception\ApiException;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Search\Indexer\AbstractNodeIndexer;
use Neos\ContentRepository\Search\Indexer\BulkNodeIndexerInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Utility\Exception\FilesException;
use Neos\Flow\Log\Utility\LogEnvironment;
use Psr\Log\LoggerInterface;

/**
 * Indexer for Content Repository Nodes. Triggered from the NodeIndexingManager.
 *
 * Internally, uses a bulk request.
 *
 * @Flow\Scope("singleton")
 */
class NodeIndexer extends AbstractNodeIndexer implements BulkNodeIndexerInterface
{
    /**
     * @Flow\Inject
     * @var NodeTypeMappingBuilderInterface
     */
    protected $nodeTypeMappingBuilder;

    /**
     * Optional postfix for the index, e.g. to have different indexes by timestamp.
     *
     * @var string
     */
    protected $indexNamePostfix = '';

    /**
     * @Flow\Inject
     * @var ErrorHandlingService
     */
    protected $errorHandlingService;

    /**
     * @Flow\Inject
     * @var ElasticSearchClient
     */
    protected $searchClient;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @var DocumentDriverInterface
     * @Flow\Inject
     */
    protected $documentDriver;

    /**
     * @var IndexerDriverInterface
     * @Flow\Inject
     */
    protected $indexerDriver;

    /**
     * @var IndexDriverInterface
     * @Flow\Inject
     */
    protected $indexDriver;

    /**
     * @var RequestDriverInterface
     * @Flow\Inject
     */
    protected $requestDriver;

    /**
     * @var SystemDriverInterface
     * @Flow\Inject
     */
    protected $systemDriver;

    /**
     * @var array
     * @Flow\InjectConfiguration(package="Flowpack.ElasticSearch.ContentRepositoryAdaptor", path="indexing.batchSize")
     */
    protected $batchSize;

    /**
     * @var array
     * @Flow\InjectConfiguration(package="Flowpack.ElasticSearch", path="indexes")
     */
    protected $indexConfiguration;

    /**
     * The current Elasticsearch bulk request, in the format required by http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/docs-bulk.html
     *
     * @var array
     */
    protected $currentBulkRequest = [];

    /**
     * @var boolean
     */
    protected $bulkProcessing = false;

    /**
     * @Flow\Inject
     * @var DimensionsService
     */
    protected $dimensionService;

    /**
     * @Flow\Inject
     * @var NodeTypeIndexingConfiguration
     */
    protected $nodeTypeIndexingConfiguration;

    /**
     * @Flow\Inject
     * @var ErrorStorageInterface
     */
    protected $errorStorage;

    public function setDimensions(array $dimensionsValues): void
    {
        $this->searchClient->setDimensions($dimensionsValues);
    }

    /**
     * Returns the index name to be used for indexing, with optional indexNamePostfix appended.
     *
     * @return string
     * @throws Exception\ConfigurationException
     * @throws Exception
     */
    public function getIndexName(): string
    {
        $indexName = $this->searchClient->getIndexName();
        if ($this->indexNamePostfix !== '') {
            $indexName .= IndexNameService::INDEX_PART_SEPARATOR . $this->indexNamePostfix;
        }

        return $indexName;
    }

    /**
     * Set the postfix for the index name
     *
     * @param string $indexNamePostfix
     * @return void
     */
    public function setIndexNamePostfix(string $indexNamePostfix): void
    {
        $this->indexNamePostfix = $indexNamePostfix;
    }

    /**
     * Return the currently active index to be used for indexing
     *
     * @return Index
     * @throws Exception
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws Exception\ConfigurationException
     */
    public function getIndex(): Index
    {
        $index = $this->searchClient->findIndex($this->getIndexName());

        $perDimensionConfiguration = $this->indexConfiguration[$this->searchClient->getBundle()][$this->searchClient->getIndexName()] ?? null;
        if ($perDimensionConfiguration !== null) {
            $index->setSettingsKey($this->searchClient->getIndexName());
        } else {
            $index->setSettingsKey($this->searchClient->getIndexNamePrefix());
        }

        return $index;
    }

    /**
     * Index this node, and add it to the current bulk request.
     *
     * @param NodeInterface $node
     * @param string $targetWorkspaceName In case indexing is triggered during publishing, a target workspace name will be passed in
     * @return void
     * @throws Exception
     */
    public function indexNode(NodeInterface $node, $targetWorkspaceName = null): void
    {
        if ($this->nodeTypeIndexingConfiguration->isIndexable($node->getNodeType()) === false) {
            $this->logger->debug(sprintf('Node "%s" (%s) skipped, Node Type is not allowed in the index.', $node->getContextPath(), $node->getNodeType()), LogEnvironment::fromMethodName(__METHOD__));
            return;
        }

        $indexer = function (NodeInterface $node, $targetWorkspaceName = null) {
            if ($this->settings['indexAllWorkspaces'] === false) {
                // we are only supposed to index the live workspace.
                // We need to check the workspace at two occasions; checking the
                // $targetWorkspaceName and the workspace name of the node's context as fallback
                if ($targetWorkspaceName !== null && $targetWorkspaceName !== 'live') {
                    return;
                }

                if ($targetWorkspaceName === null && $node->getContext()->getWorkspaceName() !== 'live') {
                    return;
                }
            }

            $documentIdentifier = $this->calculateDocumentIdentifier($node, $targetWorkspaceName);
            $nodeType = $node->getNodeType();

            $mappingType = $this->getIndex()->findType($nodeType->getName());

            $fulltextIndexOfNode = [];
            $nodePropertiesToBeStoredInIndex = $this->extractPropertiesAndFulltext($node, $fulltextIndexOfNode, function ($propertyName) use ($documentIdentifier, $node) {
                $this->logger->debug(sprintf('Property "%s" not indexed because no configuration found, node type %s.', $propertyName, $node->getNodeType()->getName()), LogEnvironment::fromMethodName(__METHOD__));
            });

            $document = new ElasticSearchDocument(
                $mappingType,
                $nodePropertiesToBeStoredInIndex,
                $documentIdentifier
            );

            $documentData = $document->getData();
            if ($targetWorkspaceName !== null) {
                $documentData['neos_workspace'] = $targetWorkspaceName;
            }

            $this->toBulkRequest($node, $this->indexerDriver->document($this->getIndexName(), $node, $document, $documentData));

            if ($this->isFulltextEnabled($node)) {
                $this->toBulkRequest($node, $this->indexerDriver->fulltext($node, $fulltextIndexOfNode, $targetWorkspaceName));
            }
        };

        $handleNode = function (NodeInterface $node, Context $context) use ($targetWorkspaceName, $indexer) {
            $nodeFromContext = $context->getNodeByIdentifier($node->getIdentifier());
            if ($nodeFromContext instanceof NodeInterface) {
                if ($node->getPath() !== $nodeFromContext->getPath()) {
                    // If the node from context does have a different path, purge the context cache and re-fetch

                    // TODO: find a better way to handle this
                    $context->getFirstLevelNodeCache()->flush();
                    $nodeFromContext = $context->getNodeByIdentifier($node->getIdentifier());
                }
                $this->searchClient->withDimensions(static function () use ($indexer, $nodeFromContext, $targetWorkspaceName) {
                    $indexer($nodeFromContext, $targetWorkspaceName);
                }, $nodeFromContext->getContext()->getTargetDimensions());
            } else {
                if ($node->isRemoved()) {
                    $this->removeNode($node, $context->getWorkspaceName());
                    $this->logger->debug(sprintf('Removed node with identifier %s, no longer in workspace %s', $node->getIdentifier(), $context->getWorkspaceName()), LogEnvironment::fromMethodName(__METHOD__));
                } else {
                    $this->logger->debug(sprintf('Could not index node with identifier %s, not found in workspace %s with dimensions %s', $node->getIdentifier(), $context->getWorkspaceName(), json_encode($context->getDimensions())), LogEnvironment::fromMethodName(__METHOD__));
                }
            }
        };

        $workspaceName = $targetWorkspaceName ?: $node->getContext()->getWorkspaceName();
        $dimensionCombinations = $this->dimensionService->getDimensionCombinationsForIndexing($node);

        if (array_filter($dimensionCombinations) === []) {
            $handleNode($node, $this->createContentContext($workspaceName));
        } else {
            foreach ($dimensionCombinations as $combination) {
                $handleNode($node, $this->createContentContext($workspaceName, $combination));
            }
        }
    }

    /**
     * @param string $workspaceName
     * @param array $dimensions
     * @return Context
     */
    protected function createContentContext(string $workspaceName, array $dimensions = []): Context
    {
        $configuration = [
            'workspaceName' => $workspaceName,
            'invisibleContentShown' => true
        ];

        if ($dimensions !== []) {
            $configuration['dimensions'] = $dimensions;
        }

        return $this->contextFactory->create($configuration);
    }

    /**
     * @param NodeInterface $node
     * @param array|null $requests
     * @throws Exception
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws FilesException
     */
    protected function toBulkRequest(NodeInterface $node, array $requests = null): void
    {
        if ($requests === null) {
            return;
        }

        $this->currentBulkRequest[] = new BulkRequestPart($this->dimensionService->hashByNode($node), $requests);
        $this->flushIfNeeded();
    }

    /**
     * Returns a stable identifier for the Elasticsearch document representing the node
     *
     * @param NodeInterface $node
     * @param string $targetWorkspaceName
     * @return string
     * @throws IllegalObjectTypeException
     */
    protected function calculateDocumentIdentifier(NodeInterface $node, $targetWorkspaceName = null): string
    {
        $workspaceName = $targetWorkspaceName ?: $node->getWorkspace()->getName();
        $nodeIdentifier = $node->getIdentifier();

        return sha1($nodeIdentifier . $workspaceName);
    }

    /**
     * Schedule node removal into the current bulk request.
     *
     * @param NodeInterface $node
     * @param string $targetWorkspaceName
     * @return void
     * @throws Exception
     * @throws FilesException
     * @throws IllegalObjectTypeException
     * @throws \Flowpack\ElasticSearch\Exception
     */
    public function removeNode(NodeInterface $node, string $targetWorkspaceName = null): void
    {
        if ($this->settings['indexAllWorkspaces'] === false) {
            // we are only supposed to index the live workspace.
            // We need to check the workspace at two occasions; checking the
            // $targetWorkspaceName and the workspace name of the node's context as fallback
            if ($targetWorkspaceName !== null && $targetWorkspaceName !== 'live') {
                return;
            }

            if ($targetWorkspaceName === null && $node->getContext()->getWorkspaceName() !== 'live') {
                return;
            }
        }

        $documentIdentifier = $this->calculateDocumentIdentifier($node, $targetWorkspaceName);

        $this->toBulkRequest($node, $this->documentDriver->delete($node, $documentIdentifier));
        $this->toBulkRequest($node, $this->indexerDriver->fulltext($node, [], $targetWorkspaceName));

        $this->logger->debug(sprintf('NodeIndexer (%s): Removed node %s (%s) from index.', $documentIdentifier, $node->getContextPath(), $node->getIdentifier()), LogEnvironment::fromMethodName(__METHOD__));
    }

    /**
     * @throws Exception
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws FilesException
     */
    protected function flushIfNeeded(): void
    {
        if ($this->bulkRequestLength() >= $this->batchSize['elements'] || $this->bulkRequestSize() >= $this->batchSize['octets']) {
            $this->flush();
        }
    }

    protected function bulkRequestSize(): int
    {
        return array_reduce($this->currentBulkRequest, static function ($sum, BulkRequestPart $request) {
            return $sum + $request->getSize();
        }, 0);
    }

    /**
     * @return int
     */
    protected function bulkRequestLength(): int
    {
        return count($this->currentBulkRequest);
    }

    /**
     * Perform the current bulk request
     *
     * @return void
     * @throws Exception
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws Exception\ConfigurationException
     */
    public function flush(): void
    {
        $bulkRequest = $this->currentBulkRequest;
        $bulkRequestSize = $this->bulkRequestLength();
        if ($bulkRequestSize === 0) {
            return;
        }

        $this->logger->debug(
            vsprintf(
                'Flush bulk request, elements=%d, maximumElements=%s, octets=%d, maximumOctets=%d',
                [$bulkRequestSize, $this->batchSize['elements'], $this->bulkRequestSize(), $this->batchSize['octets']]
            ),
            LogEnvironment::fromMethodName(__METHOD__)
        );

        $payload = [];
        /** @var BulkRequestPart $bulkRequestPart */
        foreach ($bulkRequest as $bulkRequestPart) {
            if (!$bulkRequestPart instanceof BulkRequestPart) {
                throw new \RuntimeException('Invalid bulk request part', 1577016145);
            }

            $hash = $bulkRequestPart->getTargetDimensionsHash();

            if (!isset($payload[$hash])) {
                $payload[$hash] = [];
            }

            foreach ($bulkRequestPart->getRequest() as $bulkRequestItem) {
                if ($bulkRequestItem === null) {
                    $this->logger->error('Indexing Error: A bulk request item could not be encoded as JSON', LogEnvironment::fromMethodName(__METHOD__));
                    continue 2;
                }
                $payload[$hash][] = $bulkRequestItem;
            }
        }

        if ($payload === []) {
            $this->reset();
            return;
        }

        foreach ($this->dimensionService->getDimensionsRegistry() as $hash => $dimensions) {
            if (!isset($payload[$hash])) {
                continue;
            }

            $this->searchClient->setDimensions($dimensions);
            $response = $this->requestDriver->bulk($this->getIndex(), implode(chr(10), $payload[$hash]));

            if (isset($response['errors']) && $response['errors'] !== false) {
                foreach ($response['items'] as $responseInfo) {
                    if ((int)current($responseInfo)['status'] > 299) {
                        $this->errorHandlingService->log($this->errorStorage->logErrorResult($responseInfo), LogEnvironment::fromMethodName(__METHOD__));
                    }
                }
            }
        }

        $this->reset();
    }

    protected function reset(): void
    {
        $this->dimensionService->reset();
        $this->currentBulkRequest = [];
    }

    /**
     * Update the index alias
     *
     * @return void
     * @throws Exception
     * @throws ApiException
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws \Exception
     */
    public function updateIndexAlias(): void
    {
        $aliasName = $this->searchClient->getIndexName(); // The alias name is the unprefixed index name
        if ($this->getIndexName() === $aliasName) {
            throw new Exception('UpdateIndexAlias is only allowed to be called when setIndexNamePostfix() has been called.', 1383649061);
        }

        if (!$this->getIndex()->exists()) {
            throw new Exception(sprintf('The target index "%s" does not exist.', $this->getIndex()->getName()), 1383649125);
        }

        $aliasActions = [];
        try {
            $indexNames = $this->indexDriver->getIndexNamesByAlias($aliasName);
            if ($indexNames === []) {
                // if there is an actual index with the name we want to use as alias, remove it now
                $this->indexDriver->deleteIndex($aliasName);
            } else {
                // Remove all existing aliasses
                foreach ($indexNames as $indexName) {
                    $aliasActions[] = [
                        'remove' => [
                            'index' => $indexName,
                            'alias' => $aliasName
                        ]
                    ];
                }
            }
        } catch (ApiException $exception) {
            // in case of 404, do not throw an error...
            if ($exception->getResponse()->getStatusCode() !== 404) {
                throw $exception;
            }
        }

        $aliasActions[] = [
            'add' => [
                'index' => $this->getIndexName(),
                'alias' => $aliasName
            ]
        ];

        $this->indexDriver->aliasActions($aliasActions);
    }

    /**
     * Update the main alias to allow to query all indices at once
     * @throws Exception
     * @throws Exception\ConfigurationException
     */
    public function updateMainAlias(): void
    {
        $aliasActions = [];
        $aliasNamePrefix = $this->searchClient->getIndexNamePrefix(); // The alias name is the unprefixed index name

        $indexNames = IndexNameService::filterIndexNamesByPostfix($this->indexDriver->getIndexNamesByPrefix($aliasNamePrefix), $this->indexNamePostfix);

        $cleanupAlias = function ($alias) use (&$aliasActions) {
            try {
                $indexNames = $this->indexDriver->getIndexNamesByAlias($alias);
                if ($indexNames === []) {
                    // if there is an actual index with the name we want to use as alias, remove it now
                    $this->indexDriver->deleteIndex($alias);
                } else {
                    foreach ($indexNames as $indexName) {
                        $aliasActions[] = [
                            'remove' => [
                                'index' => $indexName,
                                'alias' => $alias
                            ]
                        ];
                    }
                }
            } catch (ApiException $exception) {
                // in case of 404, do not throw an error...
                if ($exception->getResponse()->getStatusCode() !== 404) {
                    throw $exception;
                }
            }
        };

        $postfix = function ($alias) {
            return $alias . IndexNameService::INDEX_PART_SEPARATOR . $this->indexNamePostfix;
        };

        if (\count($indexNames) > 0) {
            $cleanupAlias($aliasNamePrefix);
            $cleanupAlias($postfix($aliasNamePrefix));

            foreach ($indexNames as $indexName) {
                $aliasActions[] = [
                    'add' => [
                        'index' => $indexName,
                        'alias' => $aliasNamePrefix
                    ]
                ];
                $aliasActions[] = [
                    'add' => [
                        'index' => $indexName,
                        'alias' => $postfix($aliasNamePrefix)
                    ]
                ];
            }
        }

        $this->indexDriver->aliasActions($aliasActions);
    }

    /**
     * Remove old indices which are not active anymore (remember, each bulk index creates a new index from scratch,
     * making the "old" index a stale one).
     *
     * @return array<string> a list of index names which were removed
     * @throws Exception
     * @throws Exception\ConfigurationException
     */
    public function removeOldIndices(): array
    {
        $aliasName = $this->searchClient->getIndexName(); // The alias name is the unprefixed index name

        $currentlyLiveIndices = $this->indexDriver->getIndexNamesByAlias($aliasName);

        $indexStatus = $this->systemDriver->status();
        $allIndices = array_keys($indexStatus['indices']);

        $indicesToBeRemoved = [];

        foreach ($allIndices as $indexName) {
            if (strpos($indexName, $aliasName . IndexNameService::INDEX_PART_SEPARATOR) !== 0) {
                // filter out all indices not starting with the alias-name, as they are unrelated to our application
                continue;
            }

            if (in_array($indexName, $currentlyLiveIndices, true)) {
                // skip the currently live index names from deletion
                continue;
            }

            $indicesToBeRemoved[] = $indexName;
        }

        array_map(function ($index) {
            $this->indexDriver->deleteIndex($index);
        }, $indicesToBeRemoved);

        return $indicesToBeRemoved;
    }

    /**
     * Perform indexing without checking about duplication document
     *
     * This is used during bulk indexing to improve performance
     *
     * @param callable $callback
     * @throws \Exception
     */
    public function withBulkProcessing(callable $callback): void
    {
        $bulkProcessing = $this->bulkProcessing;
        $this->bulkProcessing = true;
        try {
            /** @noinspection PhpUndefinedMethodInspection */
            $callback->__invoke();
        } catch (\Exception $exception) {
            $this->bulkProcessing = $bulkProcessing;
            throw $exception;
        }
        $this->bulkProcessing = $bulkProcessing;
    }
}
