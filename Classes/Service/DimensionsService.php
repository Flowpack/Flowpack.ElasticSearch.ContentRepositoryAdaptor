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
use Neos\ContentRepository\Utility;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class DimensionsService
{
    /**
     * @var array
     */
    protected $lastTargetDimensions;

    /**
     * @var array
     */
    protected $dimensionsRegistry = [];

    public function hash(array $dimensionValues): ?string
    {
        if ($dimensionValues === []) {
            return null;
        }
        $this->lastTargetDimensions = array_map(static function ($dimensionValues) {
            return [\is_array($dimensionValues) ? array_shift($dimensionValues) : $dimensionValues];
        }, $dimensionValues);

        $hash = Utility::sortDimensionValueArrayAndReturnDimensionsHash($this->lastTargetDimensions);
        $this->dimensionsRegistry[$hash] = $this->lastTargetDimensions;
        return $hash;
    }

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

    /**
     * @return array
     */
    public function getLastTargetDimensions(): array
    {
        return $this->lastTargetDimensions;
    }

    public function reset()
    {
        $this->dimensionsRegistry = [];
        $this->lastTargetDimensions = null;
    }
}
