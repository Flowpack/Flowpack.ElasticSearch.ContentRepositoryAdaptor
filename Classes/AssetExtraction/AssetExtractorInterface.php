<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\AssetExtraction;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Dto\AssetContent;
use Neos\Media\Domain\Model\AssetInterface;

interface AssetExtractorInterface
{
    /**
     * @param AssetInterface $asset
     * @return AssetContent
     */
    public function extract(AssetInterface $asset): AssetContent;
}
