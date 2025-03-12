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

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindChildNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepository\Search\Indexer\NodeIndexingManager;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;

/**
 * Workspace Indexer for Content Repository Nodes.
 *
 * @Flow\Scope("singleton")
 */
final class WorkspaceIndexer
{
    /**
     * @var NodeIndexingManager
     * @Flow\Inject
     */
    protected $nodeIndexingManager;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    /**
     * @param string $workspaceName
     * @param integer $limit
     * @param callable $callback
     * @return integer
     */
    public function index(ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName, $limit = null, ?callable $callback = null): int
    {
        $count = 0;
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $dimensionSpacePoints = $contentRepository->getVariationGraph()->getDimensionSpacePoints();

        if ($dimensionSpacePoints->isEmpty()) {
            $count += $this->indexWithDimensions($contentRepositoryId, $workspaceName, DimensionSpacePoint::createWithoutDimensions(), $limit, $callback);
        } else {
            foreach ($dimensionSpacePoints as $dimensionSpacePoint) {
                $count += $this->indexWithDimensions($contentRepositoryId, $workspaceName, $dimensionSpacePoint, $limit, $callback);
            }
        }

        return $count;
    }

    /**
     * @param string $workspaceName
     * @param array $dimensions
     * @param int|null $limit
     * @param callable $callback
     * @return int
     */
    public function indexWithDimensions(ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName, DimensionSpacePoint $dimensionSpacePoint, ?int $limit = null, ?callable $callback = null): int
    {
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);

        $rootNodeAggregate = $contentRepository->getContentGraph($workspaceName)->findRootNodeAggregateByType(NodeTypeName::fromString('Neos.Neos:Sites'));
        $subgraph = $contentRepository->getContentGraph($workspaceName)->getSubgraph($dimensionSpacePoint, VisibilityConstraints::withoutRestrictions());
        $rootNode = $subgraph->findNodeById($rootNodeAggregate->nodeAggregateId);
        $indexedNodes = 0;

        $traverseNodes = function (Node $currentNode, &$indexedNodes) use ($subgraph, $limit, &$traverseNodes) {
            if ($limit !== null && $indexedNodes > $limit) {
                return;
            }

            $this->nodeIndexingManager->indexNode($currentNode);
            $indexedNodes++;

            array_map(function (Node $childNode) use ($traverseNodes, &$indexedNodes) {
                $traverseNodes($childNode, $indexedNodes);
            }, iterator_to_array($subgraph->findChildNodes($currentNode->aggregateId, FindChildNodesFilter::create())->getIterator()));
        };

        $traverseNodes($rootNode, $indexedNodes);

        $this->nodeIndexingManager->flushQueues();

        if ($callback !== null) {
            $callback($workspaceName, $indexedNodes, $dimensionSpacePoint);
        }

        return $indexedNodes;
    }
}
