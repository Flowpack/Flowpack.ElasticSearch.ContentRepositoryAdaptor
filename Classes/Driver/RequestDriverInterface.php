<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */
use Flowpack\ElasticSearch\Domain\Model\Index;

/**
 * Elasticsearch Request Driver Interface
 */
interface RequestDriverInterface
{
    /**
     * Execute a bulk request
     *
     * @param Index $index
     * @param array|string $request an array or a raw JSON request payload
     * @return array
     */
    public function bulk(Index $index, $request);
}
