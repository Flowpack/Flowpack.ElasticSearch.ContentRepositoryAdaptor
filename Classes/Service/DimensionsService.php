<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service;

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
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class DimensionsService
{
    /**
     * @var array
     */
    protected $dimensionsRegistry = [];

    /**
     * @var array
     */
    protected $dimensionCombinationsForIndexing = [];

    protected const HASH_DEFAULT = 'default';

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    /**
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @return string
     */
    public function hash(DimensionSpacePoint $dimensionSpacePoint): string
    {
        if ($dimensionSpacePoint->coordinates === []) {
            $this->dimensionsRegistry[self::HASH_DEFAULT] = [];
            return self::HASH_DEFAULT;
        }

        $this->dimensionsRegistry[$dimensionSpacePoint->hash] = $dimensionSpacePoint;

        return $dimensionSpacePoint->hash;
    }

    /**
     * @param Node $node
     * @return string|null
     */
    public function hashByNode(Node $node): ?string
    {
        return $this->hash($node->dimensionSpacePoint);
    }

    /**
     * @return array<string, DimensionSpacePoint>
     */
    public function getDimensionsRegistry(): array
    {
        return $this->dimensionsRegistry;
    }

    public function reset(): void
    {
        $this->dimensionsRegistry = [];
    }

    /**
     * Only return the dimensions of the current node and all dimensions
     * that fall back to the current nodes dimensions.
     *
     * @param Node $node
     * @return array
     */
    public function getDimensionCombinationsForIndexing(Node $node): DimensionSpacePointSet
    {
        $dimensionsHash = $this->hash($node->dimensionSpacePoint);

        if (!isset($this->dimensionCombinationsForIndexing[$dimensionsHash])) {

            $contentRepository = $this->contentRepositoryRegistry->get($node->contentRepositoryId);
            $this->dimensionCombinationsForIndexing[$dimensionsHash] = $contentRepository->getVariationGraph()->getSpecializationSet($node->dimensionSpacePoint);

        }

        return $this->dimensionCombinationsForIndexing[$dimensionsHash];
    }
}
