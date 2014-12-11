<?php
/***************************************************************
 *  (c) 2014 networkteam GmbH - all rights reserved
 ***************************************************************/

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel;


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

	protected function initialize() {
		if ($this->results === NULL) {
			$this->results = $this->elasticSearchQuery->getQueryBuilder()->fetch();
		}
	}

	/**
	 * @param \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQuery $query
	 */
	public function setQuery(ElasticSearchQuery $query) {
		$this->elasticSearchQuery = $query;
		$this->results = NULL;
	}

	/**
	 * @return \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQuery
	 */
	public function getQuery() {
		return clone $this->elasticSearchQuery;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Return the current element
	 *
	 * @link http://php.net/manual/en/iterator.current.php
	 * @return mixed Can return any type.
	 */
	public function current() {
		$this->initialize();
		return current($this->results);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Move forward to next element
	 *
	 * @link http://php.net/manual/en/iterator.next.php
	 * @return void Any returned value is ignored.
	 */
	public function next() {
		$this->initialize();
		return next($this->results);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Return the key of the current element
	 *
	 * @link http://php.net/manual/en/iterator.key.php
	 * @return mixed scalar on success, or null on failure.
	 */
	public function key() {
		$this->initialize();
		return key($this->results);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Checks if current position is valid
	 *
	 * @link http://php.net/manual/en/iterator.valid.php
	 * @return boolean The return value will be casted to boolean and then evaluated.
	 * Returns true on success or false on failure.
	 */
	public function valid() {
		$this->initialize();
		return current($this->results) !== FALSE;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Rewind the Iterator to the first element
	 *
	 * @link http://php.net/manual/en/iterator.rewind.php
	 * @return void Any returned value is ignored.
	 */
	public function rewind() {
		$this->initialize();
		reset($this->results);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Whether a offset exists
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetexists.php
	 * @param mixed $offset <p>
	 * An offset to check for.
	 * </p>
	 * @return boolean true on success or false on failure.
	 * </p>
	 * <p>
	 * The return value will be casted to boolean if non-boolean was returned.
	 */
	public function offsetExists($offset) {
		$this->initialize();
		return isset($this->results[$offset]);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to retrieve
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetget.php
	 * @param mixed $offset <p>
	 * The offset to retrieve.
	 * </p>
	 * @return mixed Can return all value types.
	 */
	public function offsetGet($offset) {
		$this->initialize();
		return $this->results[$offset];
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to set
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetset.php
	 * @param mixed $offset <p>
	 * The offset to assign the value to.
	 * </p>
	 * @param mixed $value <p>
	 * The value to set.
	 * </p>
	 * @return void
	 */
	public function offsetSet($offset, $value) {
		$this->initialize();
		$this->results[$offset] = $value;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to unset
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetunset.php
	 * @param mixed $offset <p>
	 * The offset to unset.
	 * </p>
	 * @return void
	 */
	public function offsetUnset($offset) {
		$this->initialize();
		unset($this->results[$offset]);
	}

	/**
	 * Returns the first object in the result set
	 *
	 * @return object
	 * @api
	 */
	public function getFirst() {
		$this->initialize();
		if (count($this->results) > 0) {
			return array_slice($this->results, 0, 1);
		}
	}

	/**
	 * Returns an array with the objects in the result set
	 *
	 * @return array
	 * @api
	 */
	public function toArray() {
		$this->initialize();
		return $this->results;
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Count elements of an object
	 *
	 * @link http://php.net/manual/en/countable.count.php
	 * @return int The custom count as an integer.
	 * </p>
	 * <p>
	 * The return value is cast to an integer.
	 */
	public function count() {
		if ($this->count === NULL) {
			$this->count = $this->elasticSearchQuery->getQueryBuilder()->count();
		}

		return $this->count;
	}
}