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
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

/**
 * Query Builder for ElasticSearch Queries
 */
class ElasticSearchQueryBuilder implements \TYPO3\Eel\ProtectedContextAwareInterface {

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
	 * @var integer
	 */
	protected $limit;

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
				),
				'filter' => array(
					// http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/query-dsl-bool-filter.html
					'bool' => array(
						'must' => array(),
						'should' => array(),
						'must_not' => array(),
					)
				)
			)
		),
		'fields' => array('__path')
	);

	/**
	 * @param NodeInterface $contextNode
	 */
	public function __construct(NodeInterface $contextNode) {
		// on indexing, the __parentPath is tokenized to contain ALL parent path parts,
		// e.g. /foo, /foo/bar/, /foo/bar/baz; to speed up matching.. That's why we use a simple "term" filter here.
		// http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/query-dsl-term-filter.html
		$this->queryFilter('term', array('__parentPath' => $contextNode->getPath()));

		//
		// http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/query-dsl-terms-filter.html
		$this->queryFilter('terms', array('__workspace' => array('live', $contextNode->getContext()->getWorkspace()->getName())));

		$this->contextNode = $contextNode;
	}

	/**
	 * HIGH-LEVEL API
	 */

	/**
	 * Filter by node type, taking inheritance into account.
	 *
	 * @param string $nodeType the node type to filter for
	 * @return ElasticSearchQueryBuilder
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
	 */
	public function limit($limit) {
		$currentWorkspaceNestingLevel = 1;
		$workspace = $this->contextNode->getContext()->getWorkspace();
		while ($workspace->getBaseWorkspace() !== NULL) {
			$currentWorkspaceNestingLevel ++;
			$workspace = $workspace->getBaseWorkspace();
		}

		$this->limit = $limit;

		// http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/search-request-from-size.html
		$this->request['size'] = $limit * $currentWorkspaceNestingLevel;

		return $this;
	}

	/**
	 * add an exact-match query for a given property
	 *
	 * @param $propertyName
	 * @param $propertyValue
	 * @return void
	 */
	public function exactMatch($propertyName, $propertyValue) {
		if ($propertyValue instanceof NodeInterface) {
			$propertyValue = $propertyValue->getIdentifier();
		}

		return $this->queryFilter('term', array($propertyName => $propertyValue));
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
	 * Get the ElasticSearch request as we need it
	 *
	 * @return array
	 */
	public function getRequest() {
		return $this->request;
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

	/**
	 * Log the current request to the ElasticSearch log for debugging after it has been executed.
	 *
	 * @return $this
	 */
	public function log() {
		$this->logThisQuery = TRUE;

		return $this;
	}

	/**
	 * Execute the query and return the list of nodes as result
	 *
	 * @return array<\TYPO3\TYPO3CR\Domain\Model\NodeInterface>
	 */
	public function execute() {
		$timeBefore = microtime(TRUE);
		$response = $this->elasticSearchClient->getIndex()->request('GET', '/_search', array(), json_encode($this->request));
		$timeAfterwards = microtime(TRUE);

		if ($this->logThisQuery === TRUE) {
			$this->logger->log('Query Log: ' . json_encode($this->request) . ' -- execution time: ' . (($timeAfterwards-$timeBefore)*1000) . ' ms', LOG_DEBUG);
		}

		$hits = $response->getTreatedContent()['hits'];

		if ($hits['total'] === 0) {
			return array();
		}

		$nodes = array();
		foreach ($hits['hits'] as $hit) {
			$node = $this->contextNode->getNode($hit['fields']['__path']);
			$nodes[$node->getIdentifier()] = $node;
			if (count($nodes) >= $this->limit) {
				break;
			}
		}

		return $nodes;

	}
}