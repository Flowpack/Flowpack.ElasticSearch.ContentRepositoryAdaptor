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

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContentDimensionCombinator;
use Neos\ContentRepository\Utility;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class DimensionsService
{
    /**
     * @Flow\Inject
     * @var ContentDimensionCombinator
     */
    protected $contentDimensionCombinator;

    /**
     * @var array
     */
    protected $lastTargetDimensions;

    /**
     * @var array
     */
    protected $dimensionsRegistry = [];

    /**
     * @var array
     */
    protected $dimensionCombinationsForIndexing = [];

    protected const HASH_DEFAULT = 'default';

    /**
     * @param array $dimensionValues
     * @return string
     */
    public function hash(array $dimensionValues): string
    {
        if ($dimensionValues === []) {
            $this->dimensionsRegistry[self::HASH_DEFAULT] = [];
            return self::HASH_DEFAULT;
        }

        $this->lastTargetDimensions = array_map(static function ($dimensionValues) {
            return [\is_array($dimensionValues) ? array_shift($dimensionValues) : $dimensionValues];
        }, $dimensionValues);

        $hash = Utility::sortDimensionValueArrayAndReturnDimensionsHash($this->lastTargetDimensions);
        $this->dimensionsRegistry[$hash] = $this->lastTargetDimensions;

        return $hash;
    }

    /**
     * @param NodeInterface $node
     * @return string|null
     */
    public function hashByNode(NodeInterface $node): ?string
    {
        return $this->hash($node->getContext()->getTargetDimensions());
    }

    /**
     * @return array
     */
    public function getDimensionsRegistry(): array
    {
        return $this->dimensionsRegistry;
    }

    public function reset(): void
    {
        $this->dimensionsRegistry = [];
        $this->lastTargetDimensions = null;
    }

    /**
     * Only return the dimensions of the current node and all dimensions
     * that fall back to the current nodes dimensions.
     *
     * @param NodeInterface $node
     * @return array
     */
    public function getDimensionCombinationsForIndexing(NodeInterface $node): array
    {
        $dimensionsHash = $this->hash($node->getDimensions());

        if (!isset($this->dimensionCombinationsForIndexing[$dimensionsHash])) {
            $this->dimensionCombinationsForIndexing[$dimensionsHash] = $this->reduceDimensionCombinationstoSelfAndFallback(
                $this->contentDimensionCombinator->getAllAllowedCombinations(),
                $node->getDimensions()
            );
        }

        return $this->dimensionCombinationsForIndexing[$dimensionsHash];
    }

    protected function reduceDimensionCombinationstoSelfAndFallback(array $dimensionCombinations, array $nodeDimensions): array
    {
        return array_filter($dimensionCombinations, static function (array $dimensionCombination) use ($nodeDimensions) {
            foreach ($dimensionCombination as $dimensionKey => $dimensionValues) {
                if (!isset($nodeDimensions[$dimensionKey])) {
                    return false;
                }
                if (empty(array_intersect($dimensionValues, $nodeDimensions[$dimensionKey]))) {
                    return false;
                }
            }
            return true;
        });
    }
}
