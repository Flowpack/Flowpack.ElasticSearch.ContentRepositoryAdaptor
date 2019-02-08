<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Command;

/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch.ContentRepositoryAdaptor". *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use CRON\CRLib\Utility\NodeIterator;
use CRON\CRLib\Utility\NodeQuery;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer;
use Sortable\Fixture\Node;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cli\CommandController;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Mapping\NodeTypeMappingBuilder;
use TYPO3\TYPO3CR\Domain\Model\NodeData;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Model\NodeType;
use TYPO3\TYPO3CR\Domain\Service\NodeTypeManager;
use TYPO3\TYPO3CR\Search\Indexer\NodeIndexingManager;

/**
 * Provides CLI features for index handling
 *
 * @property \TYPO3\TYPO3CR\Domain\Service\Context context
 * @Flow\Scope("singleton")
 */
class NodeIndexCommandController extends CommandController
{
    /**
     * @var string NodeType filter
     */
    private $nodeTypeFilter = null;

    /**
     * @Flow\Inject
     * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer
     */
    protected $nodeIndexer;

    /**
     * @Flow\Inject
     * @var NodeIndexingManager
     */
    protected $nodeIndexingManager;

    /**
     * @Flow\Inject
     * @var \TYPO3\TYPO3CR\Domain\Repository\WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @Flow\Inject
     * @var \TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository
     */
    protected $nodeDataRepository;

    /**
     * @Flow\Inject
     * @var \TYPO3\TYPO3CR\Domain\Factory\NodeFactory
     */
    protected $nodeFactory;

    /**
     * @Flow\Inject
     * @var \TYPO3\TYPO3CR\Domain\Service\ContextFactory
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var \TYPO3\Neos\Domain\Service\ContentDimensionPresetSourceInterface
     */
    protected $contentDimensionPresetSource;

    /**
     * @Flow\Inject
     * @var NodeTypeMappingBuilder
     */
    protected $nodeTypeMappingBuilder;

    /**
     * @var integer
     */
    protected $indexedNodes;

    /**
     * @var integer
     */
    protected $countedIndexedNodes;

    /**
     * @var integer
     */
    protected $limit;

    /**
     * @Flow\Inject
     * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @Flow\Inject
     * @var \TYPO3\Flow\Configuration\ConfigurationManager
     */
    protected $configurationManager;

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
        if ($cause === \TYPO3\Flow\Object\ObjectManagerInterface::INITIALIZATIONCAUSE_CREATED) {
            $this->settings = $this->configurationManager->getConfiguration(\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.TYPO3CR.Search');
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
     * Index all nodes by creating a new index and when everything was completed, switch the index alias.
     *
     * This command (re-)indexes all nodes contained in the content repository and sets the schema beforehand.
     *
     * @param integer $limit Amount of nodes to index at maximum
     * @param boolean $update if TRUE, do not throw away the index at the start. Should *only be used for development*.
     * @param string $workspace name of the workspace which should be indexed
     * @param string $type node type filter, e.g. TYPO3.Neos:Document
     * @return void
     */
    public function buildCommand($limit = null, $update = false, $workspace = null, $type = null)
    {
        if ($type) {
            $this->nodeTypeFilter = $type;
        } else {
            // filter out all NodeTypes which aren't configured for indexing to speedup things
            $nodeTypesToIndex = array_filter($this->nodeTypeManager->getNodeTypes(false), function ($nodeType) {
                /** @var NodeType $nodeType */
                return $nodeType->getConfiguration('search') !== false;
            });
            $this->nodeTypeFilter = array_keys($nodeTypesToIndex);
        }

        if ($update === true) {
            $this->logger->log('!!! Update Mode (Development) active!', LOG_INFO);
        } else {
            if ($type && !$update) {
                $this->outputLine('NodeType filter can only be used with the --update option');
                $this->quit(1);
            }
            $this->nodeIndexer->setIndexNamePostfix(time());
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
        $this->limit = $limit;
        $this->indexedNodes = 0;
        $this->countedIndexedNodes = 0;

        if ($workspace === null && $this->settings['indexAllWorkspaces'] === false) {
            $workspace = 'live';
        }

        if ($workspace === null) {
            // get all workspace names upfront so we can do a clearState() while processing them
            $workspaceNames = array_map(
                  function ($workspace) {
                      return $workspace->getName();
                  }, $this->workspaceRepository->findAll()->toArray()
            );
            foreach ($workspaceNames as $workspaceName) {
                $this->indexWorkspace($workspaceName);
                $count = $count + $this->countedIndexedNodes;
            }
        } else {
            $this->indexWorkspace($workspace);
            $count = $count + $this->countedIndexedNodes;
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
     * Update the Elasticsearch Mapping, without indexing anything.
     */
    public function updateMappingCommand() {
        $nodeTypeMappingCollection = $this->nodeTypeMappingBuilder->buildMappingInformation($this->nodeIndexer->getIndex());
        foreach ($nodeTypeMappingCollection as $mapping) {
            /** @var \Flowpack\ElasticSearch\Domain\Model\Mapping $mapping */
            $mapping->apply();
        }
        $this->logger->log('Updated Mapping.', LOG_INFO);
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
            $this->logger->log(sprintf('Nothing removed. ElasticSearch responded with status %s, saying "%s"', $response->status, $response->error));
        }
    }

    /**
     * @param string $workspaceName
     * @return void
     */
    protected function indexWorkspace($workspaceName)
    {
        $combinations = $this->calculateDimensionCombinations();
        if ($combinations === array()) {
            $this->indexWorkspaceWithDimensions($workspaceName);
        } else {
            foreach ($combinations as $combination) {
                $this->indexWorkspaceWithDimensions($workspaceName, $combination);
            }
        }
    }

    /**
     * @param string $workspaceName
     * @param array $dimensions
     * @return void
     */
    protected function indexWorkspaceWithDimensions($workspaceName, array $dimensions = array())
    {
        $context = $this->contextFactory->create(['workspaceName' => $workspaceName, 'dimensions' => $dimensions]);
        $this->context = $context;
        $rootNode = $context->getRootNode();

        $nodeQuery = new NodeQuery($this->nodeTypeFilter, $rootNode->getPath(), null, $workspaceName);
        $total = $nodeQuery->getCount();

        $query = $nodeQuery->getQuery();
        if ($this->limit) {
            $query->setMaxResults($this->limit);
        }

        $this->outputLine('Processing workspace: "%s" ...', [$workspaceName]);
        $this->output->progressStart($this->limit ? min($this->limit, $total) : $total);

        foreach (new NodeIterator($query) as $node) {
            if ($node && NodeIndexer::isIndexingEnabled($node) !== false) {
                $this->nodeIndexingManager->indexNode($node);
                $this->indexedNodes++;
            }
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        if ($dimensions === array()) {
            $this->outputLine('Workspace "' . $workspaceName . '" without dimensions done. (Indexed ' . $this->indexedNodes . ' nodes)');
        } else {
            $this->outputLine('Workspace "' . $workspaceName . '" and dimensions "' . json_encode($dimensions) . '" done. (Indexed ' . $this->indexedNodes . ' nodes)');
        }

        $this->countedIndexedNodes = $this->countedIndexedNodes + $this->indexedNodes;
        $this->indexedNodes = 0;
    }

    /**
     * @return array
     * @todo will went into TYPO3CR
     */
    protected function calculateDimensionCombinations()
    {
        $dimensionPresets = $this->contentDimensionPresetSource->getAllPresets();

        $dimensionValueCountByDimension = array();
        $possibleCombinationCount = 1;
        $combinations = array();

        foreach ($dimensionPresets as $dimensionName => $dimensionPreset) {
            if (isset($dimensionPreset['presets']) && !empty($dimensionPreset['presets'])) {
                $dimensionValueCountByDimension[$dimensionName] = count($dimensionPreset['presets']);
                $possibleCombinationCount = $possibleCombinationCount * $dimensionValueCountByDimension[$dimensionName];
            }
        }

        foreach ($dimensionPresets as $dimensionName => $dimensionPreset) {
            for ($i = 0; $i < $possibleCombinationCount; $i++) {
                if (!isset($combinations[$i]) || !is_array($combinations[$i])) {
                    $combinations[$i] = array();
                }

                $currentDimensionCurrentPreset = current($dimensionPresets[$dimensionName]['presets']);
                $combinations[$i][$dimensionName] = $currentDimensionCurrentPreset['values'];

                if (!next($dimensionPresets[$dimensionName]['presets'])) {
                    reset($dimensionPresets[$dimensionName]['presets']);
                }
            }
        }

        return $combinations;
    }
}
