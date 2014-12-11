<?php
/***************************************************************
 *  (c) 2014 networkteam GmbH - all rights reserved
 ***************************************************************/

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel;

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use TYPO3\Flow\Persistence\QueryInterface;

class ElasticSearchQuery implements QueryInterface {

	/**
	 * @var ElasticSearchQueryBuilder
	 */
	protected $queryBuilder;

	public function __construct(ElasticSearchQueryBuilder $elasticSearchQueryBuilder) {
		$this->queryBuilder = $elasticSearchQueryBuilder;
	}

	/**
	 * @param mixed $queryBuilder
	 */
	public function setQueryBuilder(ElasticSearchQueryBuilder $queryBuilder) {
		$this->queryBuilder = $queryBuilder;
	}

	/**
	 * @return ElasticSearchQueryBuilder
	 */
	public function getQueryBuilder() {
		return $this->queryBuilder;
	}

	/**
	 * Executes the query and returns the result.
	 *
	 * @param bool $cacheResult If the result cache should be used
	 * @return \TYPO3\Flow\Persistence\QueryResultInterface The query result
	 * @api
	 */
	public function execute($cacheResult = FALSE) {
		return new ElasticSearchQueryResult($this);
	}

	/**
	 * Returns the query result count.
	 *
	 * @return integer The query result count
	 * @api
	 */
	public function count() {
		return $this->queryBuilder->getTotalItems();
	}

	/**
	 * Sets the maximum size of the result set to limit. Returns $this to allow
	 * for chaining (fluid interface).
	 *
	 * @param integer $limit
	 * @return \TYPO3\Flow\Persistence\QueryInterface
	 * @throws \InvalidArgumentException
	 * @api
	 */
	public function setLimit($limit) {
		if ($limit < 1 || !is_int($limit)) {
			throw new \InvalidArgumentException('Expecting integer greater than zero for limit');
		}

		$this->queryBuilder->limit($limit);
	}

	/**
	 * Returns the maximum size of the result set to limit.
	 *
	 * @return integer
	 * @api
	 */
	public function getLimit() {
		return $this->queryBuilder->getLimit();
	}

	/**
	 * Sets the start offset of the result set to offset. Returns $this to
	 * allow for chaining (fluid interface).
	 *
	 * @param integer $offset
	 * @return \TYPO3\Flow\Persistence\QueryInterface
	 * @throws \InvalidArgumentException
	 * @api
	 */
	public function setOffset($offset) {
		if ($offset < 1 || !is_int($offset)) {
			throw new \InvalidArgumentException('Expecting integer greater than zero for offset');
		}

		$this->queryBuilder->from($offset);
	}

	/**
	 * Returns the start offset of the result set.
	 *
	 * @return integer
	 * @api
	 */
	public function getOffset() {
		return $this->queryBuilder->getFrom();
	}

	/**
	 * Returns the type this query cares for.
	 *
	 * @return string
	 * @api
	 */
	public function getType() {
		return 'TYPO3\TYPO3CR\Domain\Model\NodeInterface';
	}

	/**
	 * Sets the property names to order the result by. Expected like this:
	 * array(
	 *  'foo' => \TYPO3\Flow\Persistence\QueryInterface::ORDER_ASCENDING,
	 *  'bar' => \TYPO3\Flow\Persistence\QueryInterface::ORDER_DESCENDING
	 * )
	 *
	 * @param array $orderings The property names to order by
	 * @return \TYPO3\Flow\Persistence\QueryInterface
	 * @throws \TYPO3\Flow\Exception
	 * @api
	 */
	public function setOrderings(array $orderings) {
		throw new Exception('Not implemented: '. __FUNCTION__);
	}

	/**
	 * Gets the property names to order the result by, like this:
	 * array(
	 *  'foo' => \TYPO3\Flow\Persistence\QueryInterface::ORDER_ASCENDING,
	 *  'bar' => \TYPO3\Flow\Persistence\QueryInterface::ORDER_DESCENDING
	 * )
	 *
	 * @return array
	 * @throws \TYPO3\Flow\Exception
	 * @api
	 */
	public function getOrderings() {
		throw new Exception('Not implemented: '. __FUNCTION__);
	}

	/**
	 * The constraint used to limit the result set. Returns $this to allow
	 * for chaining (fluid interface).
	 *
	 * @param object $constraint Some constraint, depending on the backend
	 * @return \TYPO3\Flow\Persistence\QueryInterface
	 * @throws
	 * @api
	 */
	public function matching($constraint) {
		throw new Exception('Not implemented: '. __FUNCTION__);
	}

	/**
	 * Gets the constraint for this query.
	 *
	 * @return mixed the constraint, or null if none
	 * @throws \TYPO3\Flow\Exception
	 * @api
	 */
	public function getConstraint() {
		throw new Exception('Not implemented: '. __FUNCTION__);
	}

	/**
	 * Performs a logical conjunction of the two given constraints. The method
	 * takes one or more constraints and concatenates them with a boolean AND.
	 * It also accepts a single array of constraints to be concatenated.
	 *
	 * @param mixed $constraint1 The first of multiple constraints or an array of constraints.
	 * @return object
	 * @throws \TYPO3\Flow\Exception
	 * @api
	 */
	public function logicalAnd($constraint1) {
		throw new Exception('Not implemented: '. __FUNCTION__);
	}

	/**
	 * Performs a logical disjunction of the two given constraints. The method
	 * takes one or more constraints and concatenates them with a boolean OR.
	 * It also accepts a single array of constraints to be concatenated.
	 *
	 * @param mixed $constraint1 The first of multiple constraints or an array of constraints.
	 * @return object
	 * @throws \TYPO3\Flow\Exception
	 * @api
	 */
	public function logicalOr($constraint1) {
		throw new Exception('Not implemented: '. __FUNCTION__);
	}

	/**
	 * Performs a logical negation of the given constraint
	 *
	 * @param object $constraint Constraint to negate
	 * @return object
	 * @throws \TYPO3\Flow\Exception
	 * @api
	 */
	public function logicalNot($constraint) {
		throw new Exception('Not implemented: '. __FUNCTION__);
	}

	/**
	 * Returns an equals criterion used for matching objects against a query.
	 *
	 * It matches if the $operand equals the value of the property named
	 * $propertyName. If $operand is NULL a strict check for NULL is done. For
	 * strings the comparison can be done with or without case-sensitivity.
	 *
	 * @param string $propertyName The name of the property to compare against
	 * @param mixed $operand The value to compare with
	 * @param boolean $caseSensitive Whether the equality test should be done case-sensitive for strings
	 * @return object
	 * @todo Decide what to do about equality on multi-valued properties
	 * @throws \TYPO3\Flow\Exception
	 * @api
	 */
	public function equals($propertyName, $operand, $caseSensitive = TRUE) {
		throw new Exception('Not implemented: '. __FUNCTION__);
	}

	/**
	 * Returns a like criterion used for matching objects against a query.
	 * Matches if the property named $propertyName is like the $operand, using
	 * standard SQL wildcards.
	 *
	 * @param string $propertyName The name of the property to compare against
	 * @param string $operand The value to compare with
	 * @param boolean $caseSensitive Whether the matching should be done case-sensitive
	 * @return object
	 * @throws \TYPO3\Flow\Exception if used on a non-string property
	 * @api
	 */
	public function like($propertyName, $operand, $caseSensitive = TRUE) {
		throw new Exception('Not implemented: '. __FUNCTION__);
	}

	/**
	 * Returns a "contains" criterion used for matching objects against a query.
	 * It matches if the multivalued property contains the given operand.
	 *
	 * If NULL is given as $operand, there will never be a match!
	 *
	 * @param string $propertyName The name of the multivalued property to compare against
	 * @param mixed $operand The value to compare with
	 * @return object
	 * @throws \TYPO3\Flow\Exception if used on a single-valued property
	 * @api
	 */
	public function contains($propertyName, $operand) {
		throw new Exception('Not implemented: '. __FUNCTION__);
	}

	/**
	 * Returns an "isEmpty" criterion used for matching objects against a query.
	 * It matches if the multivalued property contains no values or is NULL.
	 *
	 * @param string $propertyName The name of the multivalued property to compare against
	 * @return boolean
	 * @throws \TYPO3\Flow\Exception if used on a single-valued property
	 * @api
	 */
	public function isEmpty($propertyName) {
		throw new Exception('Not implemented: '. __FUNCTION__);
	}

	/**
	 * Returns an "in" criterion used for matching objects against a query. It
	 * matches if the property's value is contained in the multivalued operand.
	 *
	 * @param string $propertyName The name of the property to compare against
	 * @param mixed $operand The value to compare with, multivalued
	 * @return object
	 * @throws \TYPO3\Flow\Exception if used on a multi-valued property
	 * @api
	 */
	public function in($propertyName, $operand) {
		throw new Exception('Not implemented: '. __FUNCTION__);
	}

	/**
	 * Returns a less than criterion used for matching objects against a query
	 *
	 * @param string $propertyName The name of the property to compare against
	 * @param mixed $operand The value to compare with
	 * @return object
	 * @throws \TYPO3\Flow\Exception if used on a multi-valued property or with a non-literal/non-DateTime operand
	 * @api
	 */
	public function lessThan($propertyName, $operand) {
		throw new Exception('Not implemented: '. __FUNCTION__);
	}

	/**
	 * Returns a less or equal than criterion used for matching objects against a query
	 *
	 * @param string $propertyName The name of the property to compare against
	 * @param mixed $operand The value to compare with
	 * @return object
	 * @throws \TYPO3\Flow\Exception if used on a multi-valued property or with a non-literal/non-DateTime operand
	 * @api
	 */
	public function lessThanOrEqual($propertyName, $operand) {
		throw new Exception('Not implemented: '. __FUNCTION__);
	}

	/**
	 * Returns a greater than criterion used for matching objects against a query
	 *
	 * @param string $propertyName The name of the property to compare against
	 * @param mixed $operand The value to compare with
	 * @return object
	 * @throws \TYPO3\Flow\Exception if used on a multi-valued property or with a non-literal/non-DateTime operand
	 * @api
	 */
	public function greaterThan($propertyName, $operand) {
		throw new Exception('Not implemented: '. __FUNCTION__);
	}

	/**
	 * Returns a greater than or equal criterion used for matching objects against a query
	 *
	 * @param string $propertyName The name of the property to compare against
	 * @param mixed $operand The value to compare with
	 * @return object
	 * @throws \TYPO3\Flow\Exception if used on a multi-valued property or with a non-literal/non-DateTime operand
	 * @api
	 */
	public function greaterThanOrEqual($propertyName, $operand) {
		throw new Exception('Not implemented: '. __FUNCTION__);
	}
}