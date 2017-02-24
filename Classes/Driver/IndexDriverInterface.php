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

/**
 * Elasticsearch Index Driver Interface
 */
interface IndexDriverInterface
{
    /**
     * Get the list of Indexes attached to the given alias
     *
     * @param $alias
     * @return array
     */
    public function indexesByAlias($alias);

    /**
     * Remove alias by name
     *
     * @param string $index
     * @return void
     */
    public function deleteIndex($index);

    /**
     * Execute batch aliases actions
     *
     * @param array $actions
     * @return mixed
     */
    public function aliasActions(array $actions);
}
