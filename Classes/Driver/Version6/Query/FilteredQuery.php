<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version6\Query;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\AbstractQuery;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\QueryBuildingException;

/**
 * Filtered query for Elasticsearch version 6.x
 */
class FilteredQuery extends AbstractQuery
{

    /**
     * {@inheritdoc}
     */
    public function getCountRequestAsJson(): string
    {
        $request = $this->request;
        foreach ($this->unsupportedFieldsInCountRequest as $field) {
            if (isset($request[$field])) {
                unset($request[$field]);
            }
        }

        return json_encode($request);
    }

    /**
     * {@inheritdoc}
     */
    public function size(int $size): void
    {
        $this->request['size'] = $size;
    }

    /**
     * {@inheritdoc}
     */
    public function from(int $size): void
    {
        $this->request['from'] = $size;
    }

    /**
     * {@inheritdoc}
     */
    public function fulltext(string $searchWord, array $options = []): void
    {
        $this->appendAtPath('query.bool.must', [
            'query_string' => array_merge(
                $this->queryStringParameters,
                $options,
                [ 'query' => $searchWord ]
            )
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function simpleQueryStringFulltext(string $searchWord, array $options = []): void
    {
        $this->appendAtPath('query.bool.must', [
            'simple_query_string' => array_merge(
                $this->queryStringParameters,
                $options,
                [ 'query' => $searchWord ]
            )
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function queryFilter(string $filterType, $filterOptions, string $clauseType = 'must'): void
    {
        if (!in_array($clauseType, ['must', 'should', 'must_not', 'filter'])) {
            throw new QueryBuildingException('The given clause type "' . $clauseType . '" is not supported. Must be one of "must", "should", "must_not".', 1383716082);
        }

        $this->appendAtPath('query.bool.filter.bool.' . $clauseType, [$filterType => $filterOptions]);
    }
}
