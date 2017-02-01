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
 * Elastic Index Management Driver Interface
 */
interface IndexManagementDriverInterface
{
    /**
     * Delete given indexes
     *
     * @param array $indices
     * @return void
     */
    public function delete(array $indices);

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
     * @param string $aliasName
     * @return void
     */
    public function remove($aliasName);

    /**
     * Execute batch aliases actions
     *
     * @param array $actions
     * @return mixed
     */
    public function actions(array $actions);
}
