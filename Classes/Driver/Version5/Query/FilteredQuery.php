<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version5\Query;

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
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;

/**
 * Filtered query for elastic version 5
 */
class FilteredQuery extends Version1\Query\FilteredQuery
{

    /**
     * {@inheritdoc}
     */
    public function fulltext(string $searchWord, array $options = [])
    {
        $this->appendAtPath('query.bool.must', [
            'query_string' => array_merge($options, ['query' => $searchWord])
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function queryFilter($filterType, $filterOptions, $clauseType = 'must')
    {
        if (!in_array($clauseType, ['must', 'should', 'must_not'])) {
            throw new Exception\QueryBuildingException('The given clause type "' . $clauseType . '" is not supported. Must be one of "must", "should", "must_not".', 1383716082);
        }

        $this->appendAtPath('query.bool.filter.bool.' . $clauseType, [$filterType => $filterOptions]);
    }
}
