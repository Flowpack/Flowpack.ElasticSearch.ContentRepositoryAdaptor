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

use Neos\ContentRepository\Utility;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class DimensionsService
{
    public function hash(array $dimensionValues): ?string
    {
        if ($dimensionValues === []) {
            return null;
        }
        $targetDimensions = array_map(function ($dimensionValues) {
            return [\is_array($dimensionValues) ? array_shift($dimensionValues) : $dimensionValues];
        }, $dimensionValues);

        return Utility::sortDimensionValueArrayAndReturnDimensionsHash($targetDimensions);
    }
}
