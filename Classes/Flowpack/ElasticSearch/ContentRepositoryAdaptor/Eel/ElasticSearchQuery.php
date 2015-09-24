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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use TYPO3\Flow\Persistence\QueryInterface;

/**
 * This ElasticSearchQuery object is just used inside ElasticSearchQueryResult->getQuery(), so that pagination
 * widgets etc work in the same manner for ElasticSearch results.
 */
class ElasticSearchQuery implements QueryInterface
{
    /**
     * @var ElasticSearchQueryBuilder
     */
    protected $queryBuilder;

    public function __construct(ElasticSearchQueryBuilder $elasticSearchQueryBuilder)
    {
        $this->queryBuilder = $elasticSearchQueryBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function execute($cacheResult = false)
    {
        return new ElasticSearchQueryResult($this);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        // FIXME Check that results are fetched!

        return $this->queryBuilder->getTotalItems();
    }

    /**
     * {@inheritdoc}
     */
    public function setLimit($limit)
    {
        if ($limit < 1 || !is_int($limit)) {
            throw new \InvalidArgumentException('Expecting integer greater than zero for limit');
        }

        $this->queryBuilder->limit($limit);
    }

    /**
     * {@inheritdoc}
     */
    public function getLimit()
    {
        return $this->queryBuilder->getLimit();
    }

    /**
     * {@inheritdoc}
     */
    public function setOffset($offset)
    {
        if ($offset < 1 || !is_int($offset)) {
            throw new \InvalidArgumentException('Expecting integer greater than zero for offset');
        }

        $this->queryBuilder->from($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function getOffset()
    {
        return $this->queryBuilder->getFrom();
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return 'TYPO3\TYPO3CR\Domain\Model\NodeInterface';
    }

    /**
     * {@inheritdoc}
     */
    public function setOrderings(array $orderings)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749035);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderings()
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749036);
    }

    /**
     * {@inheritdoc}
     */
    public function matching($constraint)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749037);
    }

    /**
     * {@inheritdoc}
     */
    public function getConstraint()
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749038);
    }

    /**
     * {@inheritdoc}
     */
    public function logicalAnd($constraint1)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749039);
    }

    /**
     * {@inheritdoc}
     */
    public function logicalOr($constraint1)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749040);
    }

    /**
     * {@inheritdoc}
     */
    public function logicalNot($constraint)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749041);
    }

    /**
     * {@inheritdoc}
     */
    public function equals($propertyName, $operand, $caseSensitive = true)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749042);
    }

    /**
     * {@inheritdoc}
     */
    public function like($propertyName, $operand, $caseSensitive = true)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749043);
    }

    /**
     * {@inheritdoc}
     */
    public function contains($propertyName, $operand)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749044);
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty($propertyName)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749045);
    }

    /**
     * {@inheritdoc}
     */
    public function in($propertyName, $operand)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749046);
    }

    /**
     * {@inheritdoc}
     */
    public function lessThan($propertyName, $operand)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749047);
    }

    /**
     * {@inheritdoc}
     */
    public function lessThanOrEqual($propertyName, $operand)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749048);
    }

    /**
     * {@inheritdoc}
     */
    public function greaterThan($propertyName, $operand)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749049);
    }

    /**
     * {@inheritdoc}
     */
    public function greaterThanOrEqual($propertyName, $operand)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749050);
    }

    /**
     * {@inheritdoc}
     */
    public function setDistinct($distinct = true)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749051);
    }

    /**
     * {@inheritdoc}
     */
    public function isDistinct()
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749052);
    }

    /**
     * @return ElasticSearchQueryBuilder
     */
    public function getQueryBuilder()
    {
        return $this->queryBuilder;
    }
}
