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

use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

/**
 * Index Workspace Trait
 */
trait IndexWorkspaceTrait
{
    /**
     * @Flow\Inject
     * @var \TYPO3\TYPO3CR\Domain\Service\ContextFactory
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var \TYPO3\TYPO3CR\Domain\Service\ContentDimensionCombinator
     */
    protected $contentDimensionCombinator;

    /**
     * @Flow\Inject
     * @var \TYPO3\TYPO3CR\Search\Indexer\NodeIndexingManager
     */
    protected $nodeIndexingManager;

    /**
     * @param string $workspaceName
     * @param integer $limit
     * @param callable $callback
     * @param string $dimensions
     * @return integer
     */
    protected function indexWorkspace($workspaceName, $limit = null, callable $callback = null, $dimensions = [])
    {
        $count = 0;
        if ($dimensions === []) {
            $count += $this->indexWorkspaceWithDimensions($workspaceName, [], $limit, $callback);
        } else {
            foreach ($dimensions as $dimension) {
                $count += $this->indexWorkspaceWithDimensions($workspaceName, $dimension, $limit, $callback);
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

        $traverseNodes = function (NodeInterface $currentNode, &$indexedNodes, $dimensions) use ($limit, &$traverseNodes) {
            if ($limit !== null && $indexedNodes > $limit) {
                return;
            }
            $this->nodeIndexingManager->indexNode($currentNode);
            $indexedNodes++;
            array_map(function (NodeInterface $childNode) use ($traverseNodes, &$indexedNodes, $dimensions) {
                $traverseNodes($childNode, $indexedNodes, $dimensions);
            }, $currentNode->getChildNodes());
        };

        $traverseNodes($rootNode, $indexedNodes, $dimensions);

        $this->nodeFactory->reset();
        $context->getFirstLevelNodeCache()->flush();
         
        if ($callback !== null) {
            $callback($workspaceName, $indexedNodes, $dimensions);
        }

        return $indexedNodes;
    }
}
