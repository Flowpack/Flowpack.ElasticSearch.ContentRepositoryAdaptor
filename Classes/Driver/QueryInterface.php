<?php

declare(strict_types=1);

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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;

/**
 * Query Interface
 */
interface QueryInterface
{
    /**
     * Get the current request
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Get the current request as JSON string
     *
     * @return string
     */
    public function getRequestAsJson(): string;

    /**
     * Get the current count request as JSON string
     *
     * This method must adapt the current query to be compatible with the count API
     *
     * @return string
     */
    public function getCountRequestAsJson(): string;

    /**
     * Add a sort filter to the request
     *
     * @param array $configuration
     * @return void
     * @api
     */
    public function addSortFilter(array $configuration): void;

    /**
     * Set the size (limit) of the request
     *
     * @param integer $size
     * @return void
     * @api
     */
    public function size(int $size): void;

    /**
     * Set the from (offset) of the request
     *
     * @param integer $size
     * @return void
     * @api
     */
    public function from(int $size): void;

    /**
     * Match the search word against the fulltext index
     *
     * @param string $searchWord
     * @param array $options Options to configure the query_string
     * @return void
     * @api
     */
    public function fulltext(string $searchWord, array $options = []): void;

    /**
     * Match the search word against the fulltext index using the elasticsearch
     * [simple query string query](https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-simple-query-string-query.html)
     *
     * @param string $searchWord
     * @param array $options Options to configure the query_string
     * @return void
     */
    public function simpleQueryStringFulltext(string $searchWord, array $options = []): void;

    /**
     * Configure Result Highlighting. Only makes sense in combination with fulltext(). By default, highlighting is enabled.
     * It can be disabled by calling "highlight(FALSE)".
     *
     * @param integer|boolean $fragmentSize The result fragment size for highlight snippets. If this parameter is FALSE, highlighting will be disabled.
     * @param int|null $fragmentCount The number of highlight fragments to show.
     * @param int $noMatchSize
     * @param string $field
     * @return void
     * @api
     */
    public function highlight($fragmentSize, ?int $fragmentCount = null, int $noMatchSize = 150, string $field = 'neos_fulltext.*'): void;

    /**
     * This method is used to create any kind of aggregation.
     *
     * @param string $name The name to identify the resulting aggregation
     * @param array $aggregationDefinition
     * @param string $parentPath ParentPath to define the parent of a sub aggregation
     * @return void
     * @throws Exception\QueryBuildingException
     * @api
     */
    public function aggregation(string $name, array $aggregationDefinition, string $parentPath = ''): void;

    /**
     * This method is used to create any kind of suggestion.
     *
     * @param string $name
     * @param array $suggestionDefinition
     * @return void
     * @api
     */
    public function suggestions(string $name, array $suggestionDefinition): void;

    /**
     * This method is used to define a more like this query.
     * The More Like This Query (MLT Query) finds documents that are "like" a given text
     * or a given set of documents
     *
     * @param array $like An array of strings or documents
     * @param array $fields Fields to compare other docs with
     * @param array $options Additional options for the more_like_this quey
     * @return void
     */
    public function moreLikeThis(array $like, array $fields = [], array $options = []): void;

    /**
     * Add a query filter
     *
     * @param string $filterType
     * @param mixed $filterOptions
     * @param string $clauseType one of must, should, must_not
     * @return void
     * @throws Exception\QueryBuildingException
     * @api
     */
    public function queryFilter(string $filterType, $filterOptions, string $clauseType = 'must'): void;

    /**
     * @param string $path
     * @param string|array $value
     * @return void
     */
    public function setValueByPath(string $path, $value): void;

    /**
     * Append $data to the given array at $path inside $this->request.
     *
     * Low-level method to manipulate the Elasticsearch Query
     *
     * @param string $path
     * @param array $data
     * @return void
     * @throws Exception\QueryBuildingException
     */
    public function appendAtPath(string $path, array $data): void;

    /**
     * Modify a part of the Elasticsearch Request denoted by $path, merging together
     * the existing values and the passed-in values.
     *
     * @param string $path
     * @param mixed $requestPart
     * @return $this
     */
    public function setByPath(string $path, $requestPart): QueryInterface;

    /**
     * @param array $request
     * @return void
     */
    public function replaceRequest(array $request): void;
}
