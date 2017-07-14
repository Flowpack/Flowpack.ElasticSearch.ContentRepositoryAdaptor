<?php
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

use Doctrine\Common\Collections\ArrayCollection;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\LoggerInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Mapping\NodeTypeMappingBuilder;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\IndexWorkspaceTrait;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Flowpack\ElasticSearch\Transfer\Exception\ApiException;
use Neos\ContentRepository\Domain\Factory\NodeFactory;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Domain\Service\ContentDimensionPresetSourceInterface;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Search\Indexer\NodeIndexerInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides CLI features for index handling
 *
 * @Flow\Scope("singleton")
 */
class NodeIndexCommandController extends CommandController
{
    use IndexWorkspaceTrait;

    /**
     * @Flow\Inject
     * @var NodeIndexerInterface
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
     * @var NodeTypeMappingBuilder
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
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @var array
     */
    protected $settings;

    /**
     * Called by the Flow object framework after creating the object and resolving all dependencies.
     *
     * @param integer $cause Creation cause
     */
    public function initializeObject($cause)
    {
        if ($cause === ObjectManagerInterface::INITIALIZATIONCAUSE_CREATED) {
            $this->settings = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.ContentRepository.Search');
        }
    }

    /**
     * Show the mapping which would be sent to the ElasticSearch server
     *
     * @return void
     */
    public function showMappingCommand()
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
                    $this->outputLine($warning);
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
     */
    public function indexNodeCommand($identifier, $workspace = null)
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
            foreach ($this->workspaceRepository->findAll() as $workspace) {
                $indexInWorkspace($identifier, $workspace);
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
     * @param integer $limit Amount of nodes to index at maximum
     * @param boolean $update if TRUE, do not throw away the index at the start. Should *only be used for development*.
     * @param string $workspace name of the workspace which should be indexed
     * @param string $postfix Index postfix, index with the same postfix will be deleted if exist
     * @return void
     */
    public function buildCommand($limit = null, $update = false, $workspace = null, $postfix = null)
    {
        if ($workspace !== null && $this->workspaceRepository->findByIdentifier($workspace) === null) {
            $this->logger->log('The given workspace (' . $workspace . ') does not exist.', LOG_ERR);
            $this->quit(1);
        }

        $postfix = $postfix ?: time();

        $create = function (array $dimensionsValues) use ($update, $postfix) {
            $this->nodeIndexer->setDimensions($dimensionsValues);

            if ($update === true) {
                $this->logger->log('!!! Update Mode (Development) active!', LOG_INFO);
            } else {
                $this->createNewIndex($postfix, $dimensionsValues);
            }

            $this->applyMapping();
        };

        $workspaceLogger = function ($workspaceName, $indexedNodes, $dimensions) {
            if ($dimensions === []) {
                $this->outputLine('Workspace "' . $workspaceName . '" without dimensions done. (Indexed ' . $indexedNodes . ' nodes)');
            } else {
                $this->outputLine('Workspace "' . $workspaceName . '" and dimensions "' . json_encode($dimensions) . '" done. (Indexed ' . $indexedNodes . ' nodes)');
            }
        };

        $build = function ($dimensionsValues) use ($workspace, $update, $limit, $workspaceLogger) {
            $this->nodeIndexer->setDimensions($dimensionsValues);
            $this->outputLine();
            $this->logger->log(sprintf('Indexing %snodes', ($limit !== null ? 'the first ' . $limit . ' ' : '')), LOG_INFO);

            $count = 0;

            if ($workspace === null && $this->settings['indexAllWorkspaces'] === false) {
                $workspace = 'live';
            }

            if ($workspace === null) {
                foreach ($this->workspaceRepository->findAll() as $workspace) {
                    $count += $this->indexWorkspaceWithDimensions($workspace->getName(), $dimensionsValues, $limit, $workspaceLogger);
                }
            } else {
                $count += $this->indexWorkspaceWithDimensions($workspace, $dimensionsValues, $limit, $workspaceLogger);
            }

            $this->nodeIndexingManager->flushQueues();
            $this->nodeIndexer->getIndex()->refresh();

            $this->logger->log('Done. (indexed ' . $count . ' nodes)', LOG_INFO);

            // TODO: smoke tests
            if ($update === false) {
                $this->nodeIndexer->updateIndexAlias();
            }
        };

        $combinations = new ArrayCollection($this->contentDimensionCombinator->getAllAllowedCombinations());
        $combinations->map($create);
        $combinations->map($build);
    }

    /**
     * Clean up old indexes (i.e. all but the current one)
     *
     * @return void
     */
    public function cleanupCommand()
    {
        $removed = false;
        $combinations = $this->contentDimensionCombinator->getAllAllowedCombinations();
        foreach ($combinations as $dimensionsValues) {
            try {
                $this->nodeIndexer->setDimensions($dimensionsValues);
                $indicesToBeRemoved = $this->nodeIndexer->removeOldIndices();
                if (count($indicesToBeRemoved) > 0) {
                    foreach ($indicesToBeRemoved as $indexToBeRemoved) {
                        $removed = true;
                        $this->logger->log('Removing old index ' . $indexToBeRemoved);
                    }
                }
            } catch (ApiException $exception) {
                $response = json_decode($exception->getResponse());
                $message = \sprintf('Nothing removed. ElasticSearch responded with status %s', $response->status);
                if (isset($response->error->type)) {
                    $this->logger->log(sprintf('%s, saying "%s: %s"', $message, $response->error->type, $response->error->reason));
                } else {
                    $this->logger->log(sprintf('%s, saying "%s"', $message, $response->error));
                }
            }
        }
        if ($removed === false) {
            $this->logger->log('Nothing to remove.');
        }
    }

    /**
     * Create a ContentContext based on the given workspace name
     *
     * @param string $workspaceName Name of the workspace to set for the context
     * @param array $dimensions Optional list of dimensions and their values which should be set
     * @return Context
     */
    protected function createContentContext($workspaceName, array $dimensions = [])
    {
        $contextProperties = [
            'workspaceName' => $workspaceName,
            'invisibleContentShown' => true,
            'inaccessibleContentShown' => true
        ];

        if ($dimensions !== []) {
            $contextProperties['dimensions'] = $dimensions;
            $contextProperties['targetDimensions'] = array_map(function ($dimensionValues) {
                return array_shift($dimensionValues);
            }, $dimensions);
        }

        return $this->contextFactory->create($contextProperties);
    }

    /**
     * Create a new index with the given $postfix.
     *
     * @param string $postfix
     * @param array $dimensionHash
     * @return void
     */
    protected function createNewIndex($postfix, array $dimensionValues = [])
    {
        $this->nodeIndexer->setIndexNamePostfix($postfix);
        if ($this->nodeIndexer->getIndex()->exists() === true) {
            $this->logger->log(sprintf('Deleted index with the same postfix (%s)!', $postfix), LOG_WARNING);
            $this->nodeIndexer->getIndex()->delete();
        }
        $this->nodeIndexer->getIndex()->create();
        $this->logger->log('Created index ' . $this->nodeIndexer->getIndexName(), LOG_INFO);
        $this->logger->log('+ Dimensions: ' . \json_encode($dimensionValues), LOG_INFO);
    }

    /**
     * Apply the mapping to the current index.
     *
     * @return void
     */
    protected function applyMapping()
    {
        $nodeTypeMappingCollection = $this->nodeTypeMappingBuilder->buildMappingInformation($this->nodeIndexer->getIndex());
        foreach ($nodeTypeMappingCollection as $mapping) {
            /** @var Mapping $mapping */
            $mapping->apply();
        }
        $this->logger->log('+ Updated Mapping.', LOG_INFO);
    }
}
