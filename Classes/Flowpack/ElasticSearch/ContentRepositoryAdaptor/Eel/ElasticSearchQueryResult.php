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

use TYPO3\Flow\Persistence\QueryResultInterface;

class ElasticSearchQueryResult implements QueryResultInterface {

	/**
	 * @var ElasticSearchQuery
	 */
	protected $elasticSearchQuery;

	/**
	 * @var array
	 */
	protected $results = NULL;

	/**
	 * @var integer
	 */
	protected $count = NULL;

	public function __construct(ElasticSearchQuery $elasticSearchQuery) {
		$this->elasticSearchQuery = $elasticSearchQuery;
	}

	/**
	 * Initialize the results by really executing the query
	 */
	protected function initialize() {
		if ($this->results === NULL) {
			$queryBuilder = $this->elasticSearchQuery->getQueryBuilder();
			$this->results = $queryBuilder->fetch();
			$this->count = $queryBuilder->getTotalItems();
		}
	}

	/**
	 * @return \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQuery
	 */
	public function getQuery() {
		return clone $this->elasticSearchQuery;
	}

	/**
	 * {@inheritdoc}
	 */
	public function current() {
		$this->initialize();
		return current($this->results);
	}

	/**
	 * {@inheritdoc}
	 */
	public function next() {
		$this->initialize();
		return next($this->results);
	}

	/**
	 * {@inheritdoc}
	 */
	public function key() {
		$this->initialize();
		return key($this->results);
	}

	/**
	 * {@inheritdoc}
	 */
	public function valid() {
		$this->initialize();
		return current($this->results) !== FALSE;
	}

	/**
	 * {@inheritdoc}
	 */
	public function rewind() {
		$this->initialize();
		reset($this->results);
	}

	/**
	 * {@inheritdoc}
	 */
	public function offsetExists($offset) {
		$this->initialize();
		return isset($this->results[$offset]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function offsetGet($offset) {
		$this->initialize();
		return $this->results[$offset];
	}

	/**
	 * {@inheritdoc}
	 */
	public function offsetSet($offset, $value) {
		$this->initialize();
		$this->results[$offset] = $value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function offsetUnset($offset) {
		$this->initialize();
		unset($this->results[$offset]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFirst() {
		$this->initialize();
		if (count($this->results) > 0) {
			return array_slice($this->results, 0, 1);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function toArray() {
		$this->initialize();
		return $this->results;
	}

	/**
	 * {@inheritdoc}
	 */
	public function count() {
		if ($this->count === NULL) {
			$this->count = $this->elasticSearchQuery->getQueryBuilder()->count();
		}

		return $this->count;
	}
}