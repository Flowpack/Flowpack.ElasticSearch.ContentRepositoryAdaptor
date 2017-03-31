<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\QueryInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\QueryBuildingException;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\LoggerInterface;
use TYPO3\Eel\ProtectedContextAwareInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Object\ObjectManagerInterface;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Search\Search\QueryBuilderInterface;

/**
 * Query Builder for ElasticSearch Queries
 */
class ElasticSearchQueryBuilder implements QueryBuilderInterface, ProtectedContextAwareInterface
{
    /**
     * @Flow\Inject
     * @var ElasticSearchClient
     */
    protected $elasticSearchClient;

    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * The node inside which searching should happen
     *
     * @var NodeInterface
     */
    protected $contextNode;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var boolean
     */
    protected $logThisQuery = false;

    /**
     * @var string
     */
    protected $logMessage;

    /**
     * @var integer
     */
    protected $limit;

    /**
     * @var integer
     */
    protected $from;

    /**
     * This (internal) array stores, for the last search request, a mapping from Node Identifiers
     * to the full ElasticSearch Hit which was returned.
     *
     * This is needed to e.g. use result highlighting.
     *
     * @var array
     */
    protected $elasticSearchHitsIndexedByNodeFromLastRequest;

    /**
     * The ElasticSearch request, as it is being built up.
     *
     * @var QueryInterface
     * @Flow\Inject
     */
    protected $request;

    /**
     * @var array
     */
    protected $result = [];

    /**
     * HIGH-LEVEL API
     */

    /**
     * Filter by node type, taking inheritance into account.
     *
     * @param string $nodeType the node type to filter for
     * @return ElasticSearchQueryBuilder
     * @api
     */
    public function nodeType($nodeType)
    {
        // on indexing, __typeAndSupertypes contains the typename itself and all supertypes, so that's why we can
        // use a simple term filter here.

        // http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/query-dsl-term-filter.html
        return $this->queryFilter('term', ['__typeAndSupertypes' => $nodeType]);
    }

    /**
     * Sort descending by $propertyName
     *
     * @param string $propertyName the property name to sort by
     * @return ElasticSearchQueryBuilder
     * @api
     */
    public function sortDesc($propertyName)
    {
        $configuration = [
            $propertyName => ['order' => 'desc']
        ];

        $this->sort($configuration);

        return $this;
    }

    /**
     * Sort ascending by $propertyName
     *
     * @param string $propertyName the property name to sort by
     * @return ElasticSearchQueryBuilder
     * @api
     */
    public function sortAsc($propertyName)
    {
        $configuration = [
            $propertyName => ['order' => 'asc']
        ];

        $this->sort($configuration);

        return $this;
    }

    /**
     * Add a $configuration sort filter to the request
     *
     * @param array $configuration
     * @return ElasticSearchQueryBuilder
     * @api
     */
    public function sort($configuration)
    {
        $this->request->addSortFilter($configuration);

        return $this;
    }

    /**
     * output only $limit records
     *
     * Internally, we fetch $limit*$workspaceNestingLevel records, because we fetch the *conjunction* of all workspaces;
     * and then we filter after execution when we have found the right number of results.
     *
     * This algorithm can be re-checked when https://github.com/elasticsearch/elasticsearch/issues/3300 is merged.
     *
     * @param integer $limit
     * @return ElasticSearchQueryBuilder
     * @api
     */
    public function limit($limit)
    {
        if (!$limit) {
            return $this;
        }

        $currentWorkspaceNestingLevel = 1;
        $workspace = $this->contextNode->getContext()->getWorkspace();
        while ($workspace->getBaseWorkspace() !== null) {
            $currentWorkspaceNestingLevel++;
            $workspace = $workspace->getBaseWorkspace();
        }

        $this->limit = $limit;

        // http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/search-request-from-size.html
        $this->request->size($limit * $currentWorkspaceNestingLevel);

        return $this;
    }

    /**
     * output records starting at $from
     *
     * @param integer $from
     * @return ElasticSearchQueryBuilder
     * @api
     */
    public function from($from)
    {
        if (!$from) {
            return $this;
        }

        $this->from = $from;
        $this->request->from($from);

        return $this;
    }

    /**
     * add an exact-match query for a given property
     *
     * @param string $propertyName Name of the property
     * @param mixed $value Value for comparison
     * @return ElasticSearchQueryBuilder
     * @api
     */
    public function exactMatch($propertyName, $value)
    {
        if ($value instanceof NodeInterface) {
            $value = $value->getIdentifier();
        }

        return $this->queryFilter('term', [$propertyName => $value]);
    }

    /**
     * add a range filter (gt) for the given property
     *
     * @param string $propertyName Name of the property
     * @param mixed $value Value for comparison
     * @return ElasticSearchQueryBuilder
     * @api
     */
    public function greaterThan($propertyName, $value)
    {
        return $this->queryFilter('range', [$propertyName => ['gt' => $value]]);
    }

    /**
     * add a range filter (gte) for the given property
     *
     * @param string $propertyName Name of the property
     * @param mixed $value Value for comparison
     * @return ElasticSearchQueryBuilder
     * @api
     */
    public function greaterThanOrEqual($propertyName, $value)
    {
        return $this->queryFilter('range', [$propertyName => ['gte' => $value]]);
    }

    /**
     * add a range filter (lt) for the given property
     *
     * @param string $propertyName Name of the property
     * @param mixed $value Value for comparison
     * @return ElasticSearchQueryBuilder
     * @api
     */
    public function lessThan($propertyName, $value)
    {
        return $this->queryFilter('range', [$propertyName => ['lt' => $value]]);
    }

    /**
     * add a range filter (lte) for the given property
     *
     * @param string $propertyName Name of the property
     * @param mixed $value Value for comparison
     * @return ElasticSearchQueryBuilder
     * @api
     */
    public function lessThanOrEqual($propertyName, $value)
    {
        return $this->queryFilter('range', [$propertyName => ['lte' => $value]]);
    }

    /**
     * LOW-LEVEL API
     */

    /**
     * Add a filter to query.filtered.filter
     *
     * @param string $filterType
     * @param mixed $filterOptions
     * @param string $clauseType one of must, should, must_not
     * @throws \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\QueryBuildingException
     * @return ElasticSearchQueryBuilder
     * @api
     */
    public function queryFilter($filterType, $filterOptions, $clauseType = 'must')
    {
        $this->request->queryFilter($filterType, $filterOptions, $clauseType);

        return $this;
    }

    /**
     * Append $data to the given array at $path inside $this->request.
     *
     * Low-level method to manipulate the ElasticSearch Query
     *
     * @param string $path
     * @param array $data
     * @throws \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\QueryBuildingException
     * @return ElasticSearchQueryBuilder
     */
    public function appendAtPath($path, array $data)
    {
        $this->request->appendAtPath($path, $data);

        return $this;
    }

    /**
     * Add multiple filters to query.filtered.filter
     *
     * Example Usage:
     *
     *   searchFilter = TYPO3.TypoScript:RawArray {
     *      author = 'Max'
     *      tags = TYPO3.TypoScript:RawArray {
     *        0 = 'a'
     *        1 = 'b'
     *      }
     *   }
     *
     *   searchQuery = ${Search.queryFilterMultiple(this.searchFilter)}
     *
     * @param array $data An associative array of keys as variable names and values as variable values
     * @param string $clauseType one of must, should, must_not
     * @return ElasticSearchQueryBuilder
     * @api
     */
    public function queryFilterMultiple($data, $clauseType = 'must')
    {
        foreach ($data as $key => $value) {
            if ($value !== null) {
                if (is_array($value)) {
                    $this->queryFilter('terms', [$key => $value], $clauseType);
                } else {
                    $this->queryFilter('term', [$key => $value], $clauseType);
                }
            }
        }

        return $this;
    }

    /**
     * This method adds a field based aggregation configuration. This can be used for simple
     * aggregations like terms
     *
     * Example Usage to create a terms aggregation for a property color:
     * nodes = ${Search....fieldBasedAggregation("colors", "color").execute()}
     *
     * Access all aggregation data with {nodes.aggregations} in your fluid template
     *
     * @param string $name The name to identify the resulting aggregation
     * @param string $field The field to aggregate by
     * @param string $type Aggregation type
     * @param string $parentPath
     * @param int $size The amount of buckets to return
     * @return $this
     */
    public function fieldBasedAggregation($name, $field, $type = 'terms', $parentPath = '', $size = 10)
    {
        $aggregationDefinition = [
            $type => [
                'field' => $field,
                'size' => $size
            ]
        ];

        $this->aggregation($name, $aggregationDefinition, $parentPath);

        return $this;
    }

    /**
     * This method is used to create any kind of aggregation.
     *
     * Example Usage to create a terms aggregation for a property color:
     *
     * aggregationDefinition = TYPO3.TypoScript:RawArray {
     *   terms = TYPO3.TypoScript:RawArray {
     *     field = "color"
     *   }
     * }
     *
     * nodes = ${Search....aggregation("color", this.aggregationDefinition).execute()}
     *
     * Access all aggregation data with {nodes.aggregations} in your fluid template
     *
     * @param string $name
     * @param array $aggregationDefinition
     * @param string $parentPath
     * @return $this
     * @throws QueryBuildingException
     */
    public function aggregation($name, array $aggregationDefinition, $parentPath = '')
    {
        $this->request->aggregation($name, $aggregationDefinition, $parentPath);

        return $this;
    }

    /**
     * This method is used to create a simple term suggestion.
     *
     * Example Usage of a term suggestion
     *
     * nodes = ${Search....termSuggestions("aTerm")}
     *
     * Access all suggestions data with ${Search....getSuggestions()}
     *
     * @param string $text
     * @param string $field
     * @param string $name
     * @return $this
     */
    public function termSuggestions($text, $field = '_all', $name = 'suggestions')
    {
        $suggestionDefinition = [
            'text' => $text,
            'term' => [
                'field' => $field
            ]
        ];

        $this->suggestions($name, $suggestionDefinition);

        return $this;
    }

    /**
     * This method is used to create any kind of suggestion.
     *
     * Example Usage of a term suggestion for the fulltext search
     *
     * suggestionDefinition = TYPO3.TypoScript:RawArray {
     *     text = "some text"
     *     terms = TYPO3.TypoScript:RawArray {
     *         field = "body"
     *     }
     * }
     *
     * nodes = ${Search....suggestion("my-suggestions", this.suggestionDefinition).execute()}
     *
     * Access all suggestions data with {nodes.suggestions} in your fluid template
     *
     * @param string $name
     * @param array $suggestionDefinition
     * @return $this
     */
    public function suggestions($name, array $suggestionDefinition)
    {
        $this->request->suggestions($name, $suggestionDefinition);

        return $this;
    }

    /**
     * Get the ElasticSearch request as we need it
     *
     * @return QueryInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Log the current request to the ElasticSearch log for debugging after it has been executed.
     *
     * @param string $message an optional message to identify the log entry
     * @return $this
     * @api
     */
    public function log($message = null)
    {
        $this->logThisQuery = true;
        $this->logMessage = $message;

        return $this;
    }

    /**
     * @return integer
     */
    public function getTotalItems()
    {
        if (isset($this->result['hits']['total'])) {
            return (int)$this->result['hits']['total'];
        }

        return 0;
    }

    /**
     * @return integer
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @return integer
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * This low-level method can be used to look up the full ElasticSearch hit given a certain node.
     *
     * @param NodeInterface $node
     * @return array the ElasticSearch hit for the node as array, or NULL if it does not exist.
     */
    public function getFullElasticSearchHitForNode(NodeInterface $node)
    {
        if (isset($this->elasticSearchHitsIndexedByNodeFromLastRequest[$node->getIdentifier()])) {
            return $this->elasticSearchHitsIndexedByNodeFromLastRequest[$node->getIdentifier()];
        }

        return null;
    }

    /**
     * Execute the query and return the list of nodes as result.
     *
     * This method is rather internal; just to be called from the ElasticSearchQueryResult. For the public API, please use execute()
     *
     * @return array<\TYPO3\TYPO3CR\Domain\Model\NodeInterface>
     */
    public function fetch()
    {
        $timeBefore = microtime(true);
        $request = $this->request->getRequestAsJson();
        $response = $this->elasticSearchClient->getIndex()->request('GET', '/_search', [], $request);
        $timeAfterwards = microtime(true);

        $this->result = $response->getTreatedContent();

        $this->result['nodes'] = [];
        if ($this->logThisQuery === true) {
            $this->logger->log(sprintf('Query Log (%s): %s -- execution time: %s ms -- Limit: %s -- Number of results returned: %s -- Total Results: %s',
                $this->logMessage, $request, (($timeAfterwards - $timeBefore) * 1000), $this->limit, count($this->result['hits']['hits']), $this->result['hits']['total']), LOG_DEBUG);
        }
        if (array_key_exists('hits', $this->result) && is_array($this->result['hits']) && count($this->result['hits']) > 0) {
            $this->result['nodes'] = $this->convertHitsToNodes($this->result['hits']);
        }

        return $this->result;
    }

    /**
     * Get a query result object for lazy execution of the query
     *
     * @return \Traversable<\TYPO3\Flow\Persistence\QueryResultInterface>
     * @api
     */
    public function execute()
    {
        $elasticSearchQuery = new ElasticSearchQuery($this);
        $result = $elasticSearchQuery->execute(true);

        return $result;
    }

    /**
     * Return the total number of hits for the query.
     *
     * @return integer
     * @api
     */
    public function count()
    {
        $timeBefore = microtime(true);
        $request = $this->getRequest()->getCountRequestAsJson();

        $response = $this->elasticSearchClient->getIndex()->request('GET', '/_count', [], $request);
        $timeAfterwards = microtime(true);

        $treatedContent = $response->getTreatedContent();
        $count = $treatedContent['count'];

        if ($this->logThisQuery === true) {
            $this->logger->log('Count Query Log (' . $this->logMessage . '): ' . $request . ' -- execution time: ' . (($timeAfterwards - $timeBefore) * 1000) . ' ms -- Total Results: ' . $count, LOG_DEBUG);
        }

        return $count;
    }

    /**
     * Match the searchword against the fulltext index
     *
     * @param string $searchWord
     * @return QueryBuilderInterface
     * @api
     */
    public function fulltext($searchWord)
    {
        // We automatically enable result highlighting when doing fulltext searches. It is up to the user to use this information or not use it.
        $this->request->fulltext(json_encode($searchWord));
        $this->request->highlight(150, 2);

        return $this;
    }

    /**
     * Configure Result Highlighting. Only makes sense in combination with fulltext(). By default, highlighting is enabled.
     * It can be disabled by calling "highlight(FALSE)".
     *
     * @param integer|boolean $fragmentSize The result fragment size for highlight snippets. If this parameter is FALSE, highlighting will be disabled.
     * @param integer $fragmentCount The number of highlight fragments to show.
     * @return $this
     * @api
     */
    public function highlight($fragmentSize, $fragmentCount = null)
    {
        $this->request->highlight($fragmentSize, $fragmentCount);

        return $this;
    }

    /**
     * Sets the starting point for this query. Search result should only contain nodes that
     * match the context of the given node and have it as parent node in their rootline.
     *
     * @param NodeInterface $contextNode
     * @return QueryBuilderInterface
     * @api
     */
    public function query(NodeInterface $contextNode)
    {
        // on indexing, the __parentPath is tokenized to contain ALL parent path parts,
        // e.g. /foo, /foo/bar/, /foo/bar/baz; to speed up matching.. That's why we use a simple "term" filter here.
        // http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/query-dsl-term-filter.html
        // another term filter against the path allows the context node itself to be found
        $this->queryFilter('bool', [
            'should' => [
                [
                    'term' => ['__parentPath' => $contextNode->getPath()]
                ],
                [
                    'term' => ['__path' => $contextNode->getPath()]
                ]
            ]
        ]);

        //
        // http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/query-dsl-terms-filter.html
        $this->queryFilter('terms', ['__workspace' => array_unique(['live', $contextNode->getContext()->getWorkspace()->getName()])]);

        // match exact dimension values for each dimension, this works because the indexing flattens the node variants for all dimension preset combinations
        $dimensionCombinations = $contextNode->getContext()->getDimensions();
        if (is_array($dimensionCombinations)) {
            $this->queryFilter('term', ['__dimensionCombinationHash' => md5(json_encode($dimensionCombinations))]);
        }

        $this->contextNode = $contextNode;

        return $this;
    }

    /**
     * Modify a part of the Elasticsearch Request denoted by $path, merging together
     * the existing values and the passed-in values.
     *
     * @param string $path
     * @param mixed $requestPart
     * @return $this
     */
    public function request($path, $requestPart)
    {
        $this->request->setByPath($path, $requestPart);

        return $this;
    }

    /**
     * All methods are considered safe
     *
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }

    /**
     * @param array $hits
     * @return array Array of Node objects
     */
    protected function convertHitsToNodes(array $hits)
    {
        $nodes = [];
        $elasticSearchHitPerNode = [];

        /**
         * TODO: This code below is not fully correct yet:
         *
         * We always fetch $limit * (numerOfWorkspaces) records; so that we find a node:
         * - *once* if it is only in live workspace and matches the query
         * - *once* if it is only in user workspace and matches the query
         * - *twice* if it is in both workspaces and matches the query *both times*. In this case we filter the duplicate record.
         * - *once* if it is in the live workspace and has been DELETED in the user workspace (STILL WRONG)
         * - *once* if it is in the live workspace and has been MODIFIED to NOT MATCH THE QUERY ANYMORE in user workspace (STILL WRONG)
         *
         * If we want to fix this cleanly, we'd need to do an *additional query* in order to filter all nodes from a non-user workspace
         * which *do exist in the user workspace but do NOT match the current query*. This has to be done somehow "recursively"; and later
         * we might be able to use https://github.com/elasticsearch/elasticsearch/issues/3300 as soon as it is merged.
         */
        foreach ($hits['hits'] as $hit) {
            $nodePath = current($hit['fields']['__path']);
            $node = $this->contextNode->getNode($nodePath);
            if ($node instanceof NodeInterface && !isset($nodes[$node->getIdentifier()])) {
                $nodes[$node->getIdentifier()] = $node;
                $elasticSearchHitPerNode[$node->getIdentifier()] = $hit;
                if ($this->limit > 0 && count($nodes) >= $this->limit) {
                    break;
                }
            }
        }

        if ($this->logThisQuery === true) {
            $this->logger->log('Returned nodes (' . $this->logMessage . '): ' . count($nodes), LOG_DEBUG);
        }

        $this->elasticSearchHitsIndexedByNodeFromLastRequest = $elasticSearchHitPerNode;

        return array_values($nodes);
    }

    /**
     * Proxy method to access the public method of the Request object
     *
     * This is used to call a method of a custom Request type where no corresponding wrapper method exist in the QueryBuilder.
     *
     * @param string $method
     * @param array $arguments
     * @return $this
     * @throws Exception
     */
    public function __call($method, array $arguments)
    {
        if (!method_exists($this->request, $method)) {
            throw new Exception(sprintf('Method "%s" does not exist in the current Request object "%s"', $method, get_class($this->request)), 1486763515);
        }
        call_user_func_array([$this->request, $method], $arguments);

        return $this;
    }
}
