<?php

declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Command;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception as CRAException;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\Error\ErrorInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\NodeTypeMappingBuilderInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\ErrorHandlingService;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\IndexWorkspaceTrait;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Flowpack\ElasticSearch\Transfer\Exception\ApiException;
use Neos\ContentRepository\Domain\Factory\NodeFactory;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Domain\Service\ContentDimensionPresetSourceInterface;
use Neos\ContentRepository\Search\Indexer\NodeIndexerInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Neos\Controller\CreateContentContextTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides CLI features for index handling
 *
 * @Flow\Scope("singleton")
 */
class NodeIndexCommandController extends CommandController
{
    use IndexWorkspaceTrait;

    use CreateContentContextTrait;

    /**
     * @Flow\Inject
     * @var ErrorHandlingService
     */
    protected $errorHandlingService;

    /**
     * @var NodeIndexer
     */
    protected $nodeIndexer;

    /**
     * @Flow\Inject
     * @var WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @Flow\Inject
     * @var NodeDataRepository
     */
    protected $nodeDataRepository;

    /**
     * @Flow\Inject
     * @var NodeFactory
     */
    protected $nodeFactory;

    /**
     * @Flow\Inject
     * @var ContentDimensionPresetSourceInterface
     */
    protected $contentDimensionPresetSource;

    /**
     * @Flow\Inject
     * @var NodeTypeMappingBuilderInterface
     */
    protected $nodeTypeMappingBuilder;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\Inject
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @param NodeIndexerInterface $nodeIndexer
     * @return void
     */
    public function injectNodeIndexer(NodeIndexerInterface $nodeIndexer): void
    {
        $this->nodeIndexer = $nodeIndexer;
    }

    /**
     * Called by the Flow object framework after creating the object and resolving all dependencies.
     *
     * @param integer $cause Creation cause
     * @throws InvalidConfigurationTypeException
     */
    public function initializeObject(int $cause): void
    {
        if ($cause === ObjectManagerInterface::INITIALIZATIONCAUSE_CREATED) {
            $this->settings = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.ContentRepository.Search');
        }
    }

    /**
     * Show the mapping which would be sent to the ElasticSearch server
     *
     * @return void
     * @throws CRAException
     */
    public function showMappingCommand(): void
    {
        $nodeTypeMappingCollection = $this->nodeTypeMappingBuilder->buildMappingInformation($this->nodeIndexer->getIndex());
        foreach ($nodeTypeMappingCollection as $mapping) {
            /** @var Mapping $mapping */
            $this->output(Yaml::dump($mapping->asArray(), 5, 2));
            $this->outputLine();
        }
        $this->outputLine('------------');

        $mappingErrors = $this->nodeTypeMappingBuilder->getLastMappingErrors();
        if ($mappingErrors->hasErrors()) {
            $this->outputLine('<b>Mapping Errors</b>');
            foreach ($mappingErrors->getFlattenedErrors() as $errors) {
                foreach ($errors as $error) {
                    $this->outputLine($error);
                }
            }
        }

        if ($mappingErrors->hasWarnings()) {
            $this->outputLine('<b>Mapping Warnings</b>');
            foreach ($mappingErrors->getFlattenedWarnings() as $warnings) {
                foreach ($warnings as $warning) {
                    $this->outputLine((string)$warning);
                }
            }
        }
    }

    /**
     * Index a single node by the given identifier and workspace name
     *
     * @param string $identifier
     * @param string $workspace
     * @return void
     * @throws StopActionException
     */
    public function indexNodeCommand(string $identifier, string $workspace = null): void
    {
        if ($workspace === null && $this->settings['indexAllWorkspaces'] === false) {
            $workspace = 'live';
        }

        $indexNode = function ($identifier, Workspace $workspace, array $dimensions) {
            $context = $this->createContentContext($workspace->getName(), $dimensions);
            $node = $context->getNodeByIdentifier($identifier);
            if ($node === null) {
                $this->outputLine('Node with the given identifier is not found.');
                $this->quit();
            }
            $this->outputLine();
            $this->outputLine('Index node "%s" (%s)', [
                $node->getLabel(),
                $node->getIdentifier(),
            ]);
            $this->outputLine('  workspace: %s', [
                $workspace->getName()
            ]);
            $this->outputLine('  node type: %s', [
                $node->getNodeType()->getName()
            ]);
            $this->outputLine('  dimensions: %s', [
                json_encode($dimensions)
            ]);
            $this->nodeIndexer->indexNode($node);
        };

        $indexInWorkspace = function ($identifier, Workspace $workspace) use ($indexNode) {
            $combinations = $this->contentDimensionCombinator->getAllAllowedCombinations();
            if ($combinations === []) {
                $indexNode($identifier, $workspace, []);
            } else {
                foreach ($combinations as $combination) {
                    $indexNode($identifier, $workspace, $combination);
                }
            }
        };

        if ($workspace === null) {
            foreach ($this->workspaceRepository->findAll() as $workspaceToIndex) {
                $indexInWorkspace($identifier, $workspaceToIndex);
            }
        } else {
            /** @var Workspace $workspaceInstance */
            $workspaceInstance = $this->workspaceRepository->findByIdentifier($workspace);
            if ($workspaceInstance === null) {
                $this->outputLine('The given workspace (%s) does not exist.', [$workspace]);
                $this->quit(1);
            }
            $indexInWorkspace($identifier, $workspaceInstance);
        }
    }

    /**
     * Index all nodes by creating a new index and when everything was completed, switch the index alias.
     *
     * This command (re-)indexes all nodes contained in the content repository and sets the schema beforehand.
     *
     * @param int $limit Amount of nodes to index at maximum
     * @param bool $update if TRUE, do not throw away the index at the start. Should *only be used for development*.
     * @param string $workspace name of the workspace which should be indexed
     * @param string $postfix Index postfix, index with the same postfix will be deleted if exist
     * @return void
     * @throws ApiException
     * @throws StopActionException
     * @throws CRAException
     */
    public function buildCommand(int $limit = null, bool $update = false, string $workspace = null, string $postfix = ''): void
    {
        if ($workspace !== null && $this->workspaceRepository->findByIdentifier($workspace) === null) {
            $this->logger->error('The given workspace (' . $workspace . ') does not exist.', LogEnvironment::fromMethodName(__METHOD__));
            $this->quit(1);
        }

        if ($update === true) {
            $this->logger->warning('!!! Update Mode (Development) active!', LogEnvironment::fromMethodName(__METHOD__));
        } else {
            $this->createNewIndex($postfix);
        }
        $this->applyMapping();

        $this->logger->info(sprintf('Indexing %snodes ... ', ($limit !== null ? 'the first ' . $limit . ' ' : '')), LogEnvironment::fromMethodName(__METHOD__));

        $count = 0;

        if ($workspace === null && $this->settings['indexAllWorkspaces'] === false) {
            $workspace = 'live';
        }

        $callback = function ($workspaceName, $indexedNodes, $dimensions) {
            if ($dimensions === []) {
                $this->outputLine('Workspace "' . $workspaceName . '" without dimensions done. (Indexed ' . $indexedNodes . ' nodes)');
            } else {
                $this->outputLine('Workspace "' . $workspaceName . '" and dimensions "' . json_encode($dimensions) . '" done. (Indexed ' . $indexedNodes . ' nodes)');
            }
        };
        if ($workspace === null) {
            foreach ($this->workspaceRepository->findAll() as $workspaceToIndex) {
                $count += $this->indexWorkspace($workspaceToIndex->getName(), $limit, $callback);
            }
        } else {
            $count += $this->indexWorkspace($workspace, $limit, $callback);
        }

        $this->nodeIndexingManager->flushQueues();

        if ($this->errorHandlingService->hasError()) {
            $this->outputLine();
            /** @var ErrorInterface $error */
            foreach ($this->errorHandlingService as $error) {
                $this->outputLine('<error>Error</error> ' . $error->message());
            }
            $this->outputLine();
            $this->outputLine('<error>Check your logs for more information</error>');
        } else {
            $this->logger->info('Done. (indexed ' . $count . ' nodes)', LogEnvironment::fromMethodName(__METHOD__));
        }
        $this->nodeIndexer->getIndex()->refresh();

        // TODO: smoke tests
        if ($update === false) {
            $this->nodeIndexer->updateIndexAlias();
        }
    }

    /**
     * Clean up old indexes (i.e. all but the current one)
     *
     * @return void
     * @throws CRAException
     */
    public function cleanupCommand(): void
    {
        try {
            $indicesToBeRemoved = $this->nodeIndexer->removeOldIndices();
            if (count($indicesToBeRemoved) > 0) {
                foreach ($indicesToBeRemoved as $indexToBeRemoved) {
                    $this->logger->info('Removing old index ' . $indexToBeRemoved, LogEnvironment::fromMethodName(__METHOD__));
                }
            } else {
                $this->logger->info('Nothing to remove.', LogEnvironment::fromMethodName(__METHOD__));
            }
        } catch (ApiException $exception) {
            $response = json_decode($exception->getResponse());
            if ($response->error instanceof \stdClass) {
                $this->logger->info(sprintf('Nothing removed. ElasticSearch responded with status %s, saying "%s: %s"', $response->status, $response->error->type, $response->error->reason), LogEnvironment::fromMethodName(__METHOD__));
            } else {
                $this->logger->info(sprintf('Nothing removed. ElasticSearch responded with status %s, saying "%s"', $response->status, $response->error), LogEnvironment::fromMethodName(__METHOD__));
            }
        }
    }

    /**
     * Create a new index with the given $postfix.
     *
     * @param string $postfix
     * @return void
     * @throws CRAException
     */
    protected function createNewIndex(string $postfix): void
    {
        $this->nodeIndexer->setIndexNamePostfix($postfix ?: (string)time());
        if ($this->nodeIndexer->getIndex()->exists() === true) {
            $this->logger->warning(sprintf('Deleted index with the same postfix (%s)!', $postfix), LogEnvironment::fromMethodName(__METHOD__));
            $this->nodeIndexer->getIndex()->delete();
        }
        $this->nodeIndexer->getIndex()->create();
        $this->logger->info('Created index ' . $this->nodeIndexer->getIndexName(), LogEnvironment::fromMethodName(__METHOD__));
    }

    /**
     * Apply the mapping to the current index.
     *
     * @return void
     * @throws CRAException
     */
    protected function applyMapping(): void
    {
        $nodeTypeMappingCollection = $this->nodeTypeMappingBuilder->buildMappingInformation($this->nodeIndexer->getIndex());
        foreach ($nodeTypeMappingCollection as $mapping) {
            /** @var Mapping $mapping */
            $mapping->apply();
        }
        $this->logger->info('Updated Mapping.', LogEnvironment::fromMethodName(__METHOD__));
    }
}
