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
    public function toArray();

    /**
     * Get the current request as JSON string
     *
     * @return string
     */
    public function getRequestAsJson();

    /**
     * Get the current count request as JSON string
     *
     * This method must adapt the current query to be compatible with the count API
     *
     * @return string
     */
    public function getCountRequestAsJson();

    /**
     * Add a sort filter to the request
     *
     * @param array $configuration
     * @return void
     * @api
     */
    public function addSortFilter($configuration);

    /**
     * Set the size (limit) of the request
     *
     * @param integer $size
     * @return void
     * @api
     */
    public function size($size);

    /**
     * Set the from (offset) of the request
     *
     * @param integer $size
     * @return void
     * @api
     */
    public function from($size);

    /**
     * Match the searchword against the fulltext index
     *
     * @param string $searchWord
     * @return void
     * @api
     */
    public function fulltext($searchWord);

    /**
     * Configure Result Highlighting. Only makes sense in combination with fulltext(). By default, highlighting is enabled.
     * It can be disabled by calling "highlight(FALSE)".
     *
     * @param integer|boolean $fragmentSize The result fragment size for highlight snippets. If this parameter is FALSE, highlighting will be disabled.
     * @param integer $fragmentCount The number of highlight fragments to show.
     * @return void
     * @api
     */
    public function highlight($fragmentSize, $fragmentCount = null);

    /**
     * This method is used to create any kind of aggregation.
     *
     * @param string $name The name to identify the resulting aggregation
     * @param array $aggregationDefinition
     * @param string $parentPath ParentPath to define the parent of a sub aggregation
     * @return void
     * @api
     * @throws Exception\QueryBuildingException
     */
    public function aggregation($name, array $aggregationDefinition, $parentPath = '');

    /**
     * This method is used to create any kind of suggestion.
     *
     * @param string $name
     * @param array $suggestionDefinition
     * @return void
     * @api
     */
    public function suggestions($name, array $suggestionDefinition);

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
    public function queryFilter($filterType, $filterOptions, $clauseType = 'must');

    /**
     * @param string $path
     * @param string $value
     * @return void
     */
    public function setValueByPath($path, $value);

    /**
     * Append $data to the given array at $path inside $this->request.
     *
     * Low-level method to manipulate the ElasticSearch Query
     *
     * @param string $path
     * @param array $data
     * @return void
     * @throws Exception\QueryBuildingException
     */
    public function appendAtPath($path, array $data);

    /**
     * Modify a part of the Elasticsearch Request denoted by $path, merging together
     * the existing values and the passed-in values.
     *
     * @param string $path
     * @param mixed $requestPart
     * @return $this
     */
    public function setByPath($path, $requestPart);

    /**
     * @param array $request
     * @return void
     */
    public function replaceRequest(array $request);
}
