<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version2\Query;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version1;

/**
 * Default Filtered Query
 */
class FilteredQuery extends Version1\Query\FilteredQuery
{
    /**
     * {@inheritdoc}
     */
    public function fulltext($searchWord)
    {
        $this->appendAtPath('query.filtered.query.bool.must', [
            'query_string' => [
                'query' => $searchWord,
                'fields' => ['__fulltext*']
            ]
        ]);
    }
}
