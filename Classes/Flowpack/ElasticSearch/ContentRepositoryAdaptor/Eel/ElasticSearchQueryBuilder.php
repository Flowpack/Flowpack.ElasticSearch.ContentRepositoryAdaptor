<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel;

/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch.ContentRepositoryAdaptor". *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\QueryBuildingException;
use TYPO3\Eel\ProtectedContextAwareInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Search\Search\QueryBuilderInterface;

/**
 * Query Builder for ElasticSearch Queries
 */
class ElasticSearchQueryBuilder implements QueryBuilderInterface, ProtectedContextAwareInterface {

	/**
	 * @Flow\Inject
	 * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient
	 */
	protected $elasticSearchClient;

	/**
	 * The node inside which searching should happen
	 *
	 * @var NodeInterface
	 */
	protected $contextNode;

	/**
	 * @Flow\Inject
	 * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\LoggerInterface
	 */
	protected $logger;

	/**
	 * @var boolean
	 */
	protected $logThisQuery = FALSE;

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
	 * These fields are not accepted in a count request and must therefore be removed before doing so
	 *
	 * @var array
	 */
	protected $unsupportedFieldsInCountRequest = array('fields', 'sort', 'from', 'size', 'highlight');

	/**
	 * Amount of total items in response without limit
	 *
	 * @var integer
	 */
	protected $totalItems;

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
	 * @var array
	 */
	protected $request = array(
		// http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/search-request-query.html
		'query' => array(
			// The top-level query we're working on is a *filtered* query, as this allows us to efficiently
			// apply *global constraints* in the form of *filters* which apply on the whole query.
			//
			// NOTE: we do NOT add a search request FILTER to the query currently, because that would mean
			// that the filters ONLY apply for query results, but NOT for facet calculation (as explained on
			// http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/search-request-filter.html)
			//
			// Reference: http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/query-dsl-filtered-query.html
			'filtered' => array(
				'query' => array(
					'bool' => array(
						'must' => array(
							array(
								'match_all' => array()
							)
						)
					)

				),
				'filter' => array(
					// http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/query-dsl-bool-filter.html
					'bool' => array(
						'must' => array(),
						'should' => array(),
						'must_not' => array(
							// Filter out all hidden elements
							array(
								'term' => array('_hidden' => TRUE)
							),
							// if now < hiddenBeforeDateTime: HIDE
							// -> hiddenBeforeDateTime > now
							array(
								'range' => array('_hiddenBeforeDateTime' => array(
									'gt' => 'now'
								))
							),
							array(
								'range' => array('_hiddenAfterDateTime' => array(
									'lt' => 'now'
								))
							),
						),
					)
				)
			)
		),
		'fields' => array('__path')
	);

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
	public function nodeType($nodeType) {
		// on indexing, __typeAndSupertypes contains the typename itself and all supertypes, so that's why we can
		// use a simple term filter here.

		// http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/query-dsl-term-filter.html
		return $this->queryFilter('term', array('__typeAndSupertypes' => $nodeType));
	}

	/**
	 * Sort descending by $propertyName
	 *
	 * @param string $propertyName the property name to sort by
	 * @return ElasticSearchQueryBuilder
	 * @api
	 */
	public function sortDesc($propertyName) {
		if (!isset($this->request['sort'])) {
			$this->request['sort'] = array();
		}

		// http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/search-request-sort.html
		$this->request['sort'][] = array(
			$propertyName => array('order' => 'desc')
		);

		return $this;
	}


	/**
	 * Sort ascending by $propertyName
	 *
	 * @param string $propertyName the property name to sort by
	 * @return ElasticSearchQueryBuilder
	 * @api
	 */
	public function sortAsc($propertyName) {
		if (!isset($this->request['sort'])) {
			$this->request['sort'] = array();
		}

		// http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/search-request-sort.html
		$this->request['sort'][] = array(
			$propertyName => array('order' => 'asc')
		);

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
	 *
	 * @param integer $limit
	 * @return ElasticSearchQueryBuilder
	 * @api
	 */
	public function limit($limit) {
		if (!$limit) {
			return $this;
		}

		$currentWorkspaceNestingLevel = 1;
		$workspace = $this->contextNode->getContext()->getWorkspace();
		while ($workspace->getBaseWorkspace() !== NULL) {
			$currentWorkspaceNestingLevel++;
			$workspace = $workspace->getBaseWorkspace();
		}

		$this->limit = $limit;

		// http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/search-request-from-size.html
		$this->request['size'] = $limit * $currentWorkspaceNestingLevel;

		return $this;
	}

	/**
	 * output records starting at $from
	 *
	 *
	 * @param integer $from
	 * @return ElasticSearchQueryBuilder
	 * @api
	 */
	public function from($from) {
		if (!$from) {
			return $this;
		}

		$this->from = $from;
		$this->request['from'] = $from;

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
	public function exactMatch($propertyName, $value) {
		if ($value instanceof NodeInterface) {
			$value = $value->getIdentifier();
		}

		return $this->queryFilter('term', array($propertyName => $value));
	}

	/**
	 * add a range filter (gt) for the given property
	 *
	 * @param string $propertyName Name of the property
	 * @param mixed $value Value for comparison
	 * @return ElasticSearchQueryBuilder
	 * @api
	 */
	public function greaterThan($propertyName, $value) {
		return $this->queryFilter('range', array($propertyName => array('gt' => $value)));
	}

	/**
	 * add a range filter (gte) for the given property
	 *
	 * @param string $propertyName Name of the property
	 * @param mixed $value Value for comparison
	 * @return ElasticSearchQueryBuilder
	 * @api
	 */
	public function greaterThanOrEqual($propertyName, $value) {
		return $this->queryFilter('range', array($propertyName => array('gte' => $value)));
	}

	/**
	 * add a range filter (lt) for the given property
	 *
	 * @param string $propertyName Name of the property
	 * @param mixed $value Value for comparison
	 * @return ElasticSearchQueryBuilder
	 * @api
	 */
	public function lessThan($propertyName, $value) {
		return $this->queryFilter('range', array($propertyName => array('lt' => $value)));
	}


	/**
	 * add a range filter (lte) for the given property
	 *
	 * @param string $propertyName Name of the property
	 * @param mixed $value Value for comparison
	 * @return ElasticSearchQueryBuilder
	 * @api
	 */
	public function lessThanOrEqual($propertyName, $value) {
		return $this->queryFilter('range', array($propertyName => array('lte' => $value)));
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
	public function queryFilter($filterType, $filterOptions, $clauseType = 'must') {
		if (!in_array($clauseType, array('must', 'should', 'must_not'))) {
			throw new QueryBuildingException('The given clause type "' . $clauseType . '" is not supported. Must be one of "mmust", "should", "must_not".', 1383716082);
		}
		return $this->appendAtPath('query.filtered.filter.bool.' . $clauseType, array($filterType => $filterOptions));
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
	public function appendAtPath($path, array $data) {
		$currentElement =& $this->request;
		foreach (explode('.', $path) as $pathPart) {
			if (!isset($currentElement[$pathPart])) {
				throw new QueryBuildingException('The element at path "' . $path . '" was not an array (failed at "' . $pathPart . '").', 1383716367);
			}
			$currentElement =& $currentElement[$pathPart];
		}
		$currentElement[] = $data;

		return $this;
	}

	/**
	 * Add multiple filters to query.filtered.filter
	 *
	 * Example Usage::
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
	public function queryFilterMultiple($data, $clauseType = 'must') {
		foreach ($data as $key => $value) {
			if ($value !== NULL) {
				if (is_array($value)) {
					$this->queryFilter('terms', array($key => $value), $clauseType);
				} else {
					$this->queryFilter('term', array($key => $value), $clauseType);
				}
			}
		}
		return $this;
	}

	/**
	 * Get the ElasticSearch request as we need it
	 *
	 * @return array
	 */
	public function getRequest() {
		return $this->request;
	}

	/**
	 * Log the current request to the ElasticSearch log for debugging after it has been executed.
	 *
	 * @param string $message an optional message to identify the log entry
	 * @return $this
	 * @api
	 */
	public function log($message = NULL) {
		$this->logThisQuery = TRUE;
		$this->logMessage = $message;

		return $this;
	}

	/**
	 * @return integer
	 */
	public function getTotalItems() {
		return $this->totalItems;
	}

	/**
	 * @return integer
	 */
	public function getLimit() {
		return $this->limit;
	}

	/**
	 * @return integer
	 */
	public function getFrom() {
		return $this->from;
	}

	/**
	 * This low-level method can be used to look up the full ElasticSearch hit given a certain node.
	 *
	 * @param NodeInterface $node
	 * @return array the ElasticSearch hit for the node as array, or NULL if it does not exist.
	 */
	public function getFullElasticSearchHitForNode(NodeInterface $node) {
		if (isset($this->elasticSearchHitsIndexedByNodeFromLastRequest[$node->getIdentifier()])) {
			return $this->elasticSearchHitsIndexedByNodeFromLastRequest[$node->getIdentifier()];
		}
		return NULL;
	}

	/**
	 * Execute the query and return the list of nodes as result.
	 *
	 * This method is rather internal; just to be called from the ElasticSearchQueryResult. For the public API, please use execute()
	 *
	 * @return array<\TYPO3\TYPO3CR\Domain\Model\NodeInterface>
	 */
	public function fetch() {
		$timeBefore = microtime(TRUE);
		$response = $this->elasticSearchClient->getIndex()->request('GET', '/_search', array(), json_encode($this->request));
		$timeAfterwards = microtime(TRUE);

		$treatedContent = $response->getTreatedContent();
		$hits = $treatedContent['hits'];

		if ($this->logThisQuery === TRUE) {
			$this->logger->log('Query Log (' . $this->logMessage . '): ' . json_encode($this->request) . ' -- execution time: ' . (($timeAfterwards - $timeBefore) * 1000) . ' ms -- Limit: ' . $this->limit . ' -- Number of results returned: ' . count($hits['hits']) . ' -- Total Results: ' . $hits['total'], LOG_DEBUG);
		}

		$this->totalItems = $hits['total'];

		if ($hits['total'] === 0) {
			return array();
		}

		$nodes = array();
		$elasticSearchHitPerNode = array();

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
			// with ElasticSearch 1.0 fields always returns an array,
			// see https://github.com/Flowpack/Flowpack.ElasticSearch.ContentRepositoryAdaptor/issues/17
			if (is_array($hit['fields']['__path'])) {
				$nodePath = current($hit['fields']['__path']);
			} else {
				$nodePath = $hit['fields']['__path'];
			}
			$node = $this->contextNode->getNode($nodePath);
			if ($node instanceof NodeInterface) {
				$nodes[$node->getIdentifier()] = $node;
				$elasticSearchHitPerNode[$node->getIdentifier()] = $hit;
				if ($this->limit > 0 && count($nodes) >= $this->limit) {
					break;
				}
			}
		}

		if ($this->logThisQuery === TRUE) {
			$this->logger->log('Query Log (' . $this->logMessage . ') Number of returned results: ' . count($nodes), LOG_DEBUG);
		}

		$this->elasticSearchHitsIndexedByNodeFromLastRequest = $elasticSearchHitPerNode;

		return array_values($nodes);
	}

	/**
	 * Get a query result object for lazy execution of the query
	 *
	 * @return \Traversable<\TYPO3\Flow\Persistence\QueryResultInterface>
	 * @api
	 */
	public function execute() {
		$elasticSearchQuery = new ElasticSearchQuery($this);
		$result = $elasticSearchQuery->execute();
		return $result;
	}

	/**
	 * Return the total number of hits for the query.
	 *
	 * @return integer
	 * @api
	 */
	public function count() {
		$timeBefore = microtime(TRUE);
		$request = $this->request;
		foreach ($this->unsupportedFieldsInCountRequest as $field) {
			if (isset($request[$field])) {
				unset($request[$field]);
			}
		}

		$response = $this->elasticSearchClient->getIndex()->request('GET', '/_count', array(), json_encode($request));
		$timeAfterwards = microtime(TRUE);

		$treatedContent = $response->getTreatedContent();
		$count = $treatedContent['count'];

		if ($this->logThisQuery === TRUE) {
			$this->logger->log('Query Log (' . $this->logMessage . '): ' . json_encode($this->request) . ' -- execution time: ' . (($timeAfterwards - $timeBefore) * 1000) . ' ms -- Total Results: ' . $count, LOG_DEBUG);
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
	public function fulltext($searchWord) {
		$this->appendAtPath('query.filtered.query.bool.must', array(
			'query_string' => array(
				'query' => $searchWord
			)
		));

		// We automatically enable result highlighting when doing fulltext searches. It is up to the user to use this information or not use it.
		return $this->highlight(150, 2);
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
	public function highlight($fragmentSize, $fragmentCount = NULL) {
		if ($fragmentSize === FALSE) {
			// Highlighting is disabled.
			unset($this->request['highlight']);
		} else {
			$this->request['highlight'] = array(
				'fields' => array(
					'__fulltext*' => array(
						'fragment_size' => $fragmentSize,
						'no_match_size' => $fragmentSize,
						'number_of_fragments' => $fragmentCount
					)
				)
			);
		}

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
	public function query(NodeInterface $contextNode) {
		// on indexing, the __parentPath is tokenized to contain ALL parent path parts,
		// e.g. /foo, /foo/bar/, /foo/bar/baz; to speed up matching.. That's why we use a simple "term" filter here.
		// http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/query-dsl-term-filter.html
		$this->queryFilter('term', array('__parentPath' => $contextNode->getPath()));

		//
		// http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/query-dsl-terms-filter.html
		$this->queryFilter('terms', array('__workspace' => array_unique(array('live', $contextNode->getContext()->getWorkspace()->getName()))));

		// match exact dimension values for each dimension, this works because the indexing flattens the node variants for all dimension preset combinations
		foreach ($contextNode->getContext()->getDimensions() as $dimensionName => $dimensionValues) {
			$this->queryFilter('terms', array('__dimensionCombinations.' . $dimensionName => $dimensionValues, 'execution' => 'and'));
		}

		$this->contextNode = $contextNode;

		return $this;
	}

	/**
	 * All methods are considered safe
	 *
	 * @param string $methodName
	 * @return boolean
	 */
	public function allowsCallOfMethod($methodName) {
		return TRUE;
	}
}