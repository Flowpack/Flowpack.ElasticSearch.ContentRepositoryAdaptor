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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Mapping\NodeTypeMappingBuilder;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\IndexWorkspaceTrait;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\ContentRepository\Domain\Model\Workspace;

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
     * @var \Neos\ContentRepository\Search\Indexer\NodeIndexerInterface
     */
    protected $nodeIndexer;

    /**
     * @Flow\Inject
     * @var \Neos\ContentRepository\Domain\Repository\WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @Flow\Inject
     * @var \Neos\ContentRepository\Domain\Repository\NodeDataRepository
     */
    protected $nodeDataRepository;

    /**
     * @Flow\Inject
     * @var \Neos\ContentRepository\Domain\Factory\NodeFactory
     */
    protected $nodeFactory;

    /**
     * @Flow\Inject
     * @var \Neos\ContentRepository\Domain\Service\ContentDimensionPresetSourceInterface
     */
    protected $contentDimensionPresetSource;

    /**
     * @Flow\Inject
     * @var NodeTypeMappingBuilder
     */
    protected $nodeTypeMappingBuilder;

    /**
     * @Flow\Inject
     * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\Inject
     * @var \Neos\Flow\Configuration\ConfigurationManager
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
        if ($cause === \Neos\Flow\ObjectManagement\ObjectManagerInterface::INITIALIZATIONCAUSE_CREATED) {
            $this->settings = $this->configurationManager->getConfiguration(\Neos\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.ContentRepository.Search');
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
            /** @var \Flowpack\ElasticSearch\Domain\Model\Mapping $mapping */
            $this->output(\Symfony\Component\Yaml\Yaml::dump($mapping->asArray(), 5, 2));
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
     * @param string $postfix Index postfix, index with the same postifix will be deleted if exist
     * @param string $dimensionsPreset Select a custom preset to limit the indexed dimension combinations
     * @return void
     */
    public function buildCommand($limit = null, $update = false, $workspace = null, $postfix = null, $dimensionsPreset = null)
    {
        if ($workspace !== null && $this->workspaceRepository->findByIdentifier($workspace) === null) {
            $this->logger->log('The given workspace (' . $workspace . ') does not exist.', LOG_ERR);
            $this->quit(1);
        }

        if ($update === true) {
            $this->logger->log('!!! Update Mode (Development) active!', LOG_INFO);
        } else {
            $this->nodeIndexer->setIndexNamePostfix($postfix ?: time());
            if ($this->nodeIndexer->getIndex()->exists() === true) {
                $this->logger->log(sprintf('Deleted index with the same postfix (%s)!', $postfix), LOG_WARNING);
                $this->nodeIndexer->getIndex()->delete();
            }
            $this->nodeIndexer->getIndex()->create();
            $this->logger->log('Created index ' . $this->nodeIndexer->getIndexName(), LOG_INFO);

            $nodeTypeMappingCollection = $this->nodeTypeMappingBuilder->buildMappingInformation($this->nodeIndexer->getIndex());
            foreach ($nodeTypeMappingCollection as $mapping) {
                /** @var \Flowpack\ElasticSearch\Domain\Model\Mapping $mapping */
                $mapping->apply();
            }
            $this->logger->log('Updated Mapping.', LOG_INFO);
        }

        $this->logger->log(sprintf('Indexing %snodes ... ', ($limit !== null ? 'the first ' . $limit . ' ' : '')), LOG_INFO);

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
            foreach ($this->workspaceRepository->findAll() as $workspace) {
                $count += $this->indexWorkspace($workspace->getName(), $limit, $callback, $dimensionsPreset);
            }
        } else {
            $count += $this->indexWorkspace($workspace, $limit, $callback, $dimensionsPreset);
        }

        $this->nodeIndexingManager->flushQueues();

        $this->logger->log('Done. (indexed ' . $count . ' nodes)', LOG_INFO);
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
     */
    public function cleanupCommand()
    {
        try {
            $indicesToBeRemoved = $this->nodeIndexer->removeOldIndices();
            if (count($indicesToBeRemoved) > 0) {
                foreach ($indicesToBeRemoved as $indexToBeRemoved) {
                    $this->logger->log('Removing old index ' . $indexToBeRemoved);
                }
            } else {
                $this->logger->log('Nothing to remove.');
            }
        } catch (\Flowpack\ElasticSearch\Transfer\Exception\ApiException $exception) {
            $response = json_decode($exception->getResponse());
            $this->logger->log(sprintf('Nothing removed. ElasticSearch responded with status %s, saying "%s: %s"', $response->status, $response->error->type, $response->error->reason));
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

}
