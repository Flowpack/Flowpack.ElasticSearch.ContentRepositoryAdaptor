<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version5;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version1;

/**
 * Index management driver for Elasticsearch version 5.x
 *
 * @Flow\Scope("singleton")
 */
class IndexDriver extends Version1\IndexDriver
{
}
