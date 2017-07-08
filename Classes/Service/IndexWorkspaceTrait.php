<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service;

/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch.ContentRepositoryAdaptor". *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Model\NodeInterface;

/**
 * Index Workspace Trait
 */
trait IndexWorkspaceTrait
{
    /**
     * @Flow\Inject
     * @var \Neos\ContentRepository\Domain\Service\ContextFactory
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var \Neos\ContentRepository\Domain\Service\ContentDimensionCombinator
     */
    protected $contentDimensionCombinator;

    /**
     * @Flow\Inject
     * @var \Neos\ContentRepository\Search\Indexer\NodeIndexingManager
     */
    protected $nodeIndexingManager;

    /**
     * @var array
     */
    protected $contentDimensionCombinationsToIndex;

    /**
     * @var array
     */
    protected $contentDimensionCombinationsToIndexHashes;

    /**
     * @param string $workspaceName
     * @param integer $limit
     * @param callable $callback
     * @return integer
     */
    protected function indexWorkspace($workspaceName, $limit = null, callable $callback = null, $selectedIndexDimensionCombinationPreset = null)
    {
        if (!$this->contentDimensionCombinationsToIndex) {
            try {
                if (is_null($selectedIndexDimensionCombinationPreset)) {
                    $selectedIndexDimensionCombinationPreset = $this->configurationManager->getConfiguration(\Neos\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
                        'Flowpack.ElasticSearch.ContentRepositoryAdaptor.indexDimensionCombinationPreset');
                }
                $this->contentDimensionCombinationsToIndex = $this->configurationManager->getConfiguration(\Neos\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
                    'Flowpack.ElasticSearch.ContentRepositoryAdaptor.indexDimensionCombinationPresets.' . $selectedIndexDimensionCombinationPreset);

                $this->contentDimensionCombinationsToIndexHashes = array();
                foreach ($this->contentDimensionCombinationsToIndex as $contentDimensionCombination) {
                    $this->contentDimensionCombinationsToIndexHashes[] = sha1(serialize($contentDimensionCombination));
                }
            } catch (\Exception $e) {
                $this->contentDimensionCombinationsToIndex = [];
            }
        }

        $count = 0;
        $combinations = $this->contentDimensionCombinator->getAllAllowedCombinations();
        if ($combinations === []) {
            $count += $this->indexWorkspaceWithDimensions($workspaceName, [], $limit, $callback);
        } else {
            foreach ($combinations as $combination) {
                if (count($this->contentDimensionCombinationsToIndex) <= 0 || in_array(sha1(serialize($combination)), $this->contentDimensionCombinationsToIndexHashes)) {
                    $count += $this->indexWorkspaceWithDimensions($workspaceName, $combination, $limit, $callback);
                }
            }
        }

        return $count;
    }

    /**
     * @param string $workspaceName
     * @param array $dimensions
     * @param integer $limit
     * @param callable $callback
     * @return integer
     */
    protected function indexWorkspaceWithDimensions($workspaceName, array $dimensions = [], $limit = null, callable $callback = null)
    {
        $context = $this->contextFactory->create(['workspaceName' => $workspaceName, 'dimensions' => $dimensions]);
        $rootNode = $context->getRootNode();
        $indexedNodes = 0;

        $traverseNodes = function (NodeInterface $currentNode, &$indexedNodes) use ($limit, &$traverseNodes) {
            if ($limit !== null && $indexedNodes > $limit) {
                return;
            }
            $this->nodeIndexingManager->indexNode($currentNode);
            $indexedNodes++;
            array_map(function (NodeInterface $childNode) use ($traverseNodes, &$indexedNodes) {
                $traverseNodes($childNode, $indexedNodes);
            }, $currentNode->getChildNodes());
        };

        $traverseNodes($rootNode, $indexedNodes);

        $this->nodeFactory->reset();
        $context->getFirstLevelNodeCache()->flush();

        if ($callback !== null) {
            $callback($workspaceName, $indexedNodes, $dimensions);
        }

        return $indexedNodes;
    }
}
