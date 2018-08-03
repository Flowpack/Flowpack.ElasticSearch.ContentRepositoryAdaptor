<?php
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

use Neos\ContentRepository\Domain\Factory\NodeFactory;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContentDimensionCombinator;
use Neos\ContentRepository\Domain\Service\ContextFactory;
use Neos\ContentRepository\Search\Indexer\NodeIndexingManager;
use Neos\Flow\Annotations as Flow;

/**
 * Workspace Indexer for Content Repository Nodes.
 *
 * @Flow\Scope("singleton")
 */
final class WorkspaceIndexer
{
    /**
     * @var ContextFactory
     * @Flow\Inject
     */
    protected $contextFactory;

    /**
     * @var ContentDimensionCombinator
     * @Flow\Inject
     */
    protected $contentDimensionCombinator;

    /**
     * @var NodeIndexingManager
     * @Flow\Inject
     */
    protected $nodeIndexingManager;

    /**
     * @var NodeFactory
     * @Flow\Inject
     */
    protected $nodeFactory;

    /**
     * @param string $workspaceName
     * @param int $limit
     * @param callable $callback
     * @return integer
     */
    public function index($workspaceName, $limit = null, callable $callback = null)
    {
        $count = 0;
        $combinations = $this->contentDimensionCombinator->getAllAllowedCombinations();
        if ($combinations === []) {
            $count += $this->indexWithDimensions($workspaceName, [], $limit, $callback);
        } else {
            foreach ($combinations as $combination) {
                $count += $this->indexWithDimensions($workspaceName, $combination, $limit, $callback);
            }
        }

        return $count;
    }

    /**
     * @param string $workspaceName
     * @param array $dimensions
     * @param int $limit
     * @param callable $callback
     * @return integer
     */
    public function indexWithDimensions($workspaceName, array $dimensions = [], $limit = null, callable $callback = null)
    {
        $context = $this->contextFactory->create([
            'workspaceName' => $workspaceName,
            'dimensions' => $dimensions,
            'invisibleContentShown' => true
        ]);
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

        $this->nodeIndexingManager->flushQueues();

        if ($callback !== null) {
            $callback($workspaceName, $indexedNodes, $dimensions);
        }

        return $indexedNodes;
    }
}
