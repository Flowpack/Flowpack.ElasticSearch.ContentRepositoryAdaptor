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

use Neos\ContentRepository\Utility;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class DimensionsService
{
    public function hash(array $dimensionValues)
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
