<?php
declare(strict_types=1);

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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use InvalidArgumentException;
use JsonException;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\QueryInterface;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Persistence\QueryResultInterface;

/**
 * This ElasticSearchQuery object is just used inside ElasticSearchQueryResult->getQuery(), so that pagination
 * widgets etc work in the same manner for Elasticsearch results.
 */
class ElasticSearchQuery implements QueryInterface
{
    /**
     * @var ElasticSearchQueryBuilder
     */
    protected $queryBuilder;

    /**
     * @var array
     */
    protected static $runtimeQueryResultCache;

    /**
     * ElasticSearchQuery constructor.
     *
     * @param ElasticSearchQueryBuilder $elasticSearchQueryBuilder
     */
    public function __construct(ElasticSearchQueryBuilder $elasticSearchQueryBuilder)
    {
        $this->queryBuilder = $elasticSearchQueryBuilder;
    }

    /**
     * Executes the query and returns the result.
     *
     * @param bool $cacheResult If the result cache should be used
     * @return ElasticSearchQueryResult The query result
     * @throws Exception
     * @throws Exception\ConfigurationException
     * @throws JsonException
     * @api
     */
    public function execute($cacheResult = false): QueryResultInterface
    {
        $queryHash = md5($this->queryBuilder->getIndexName() . json_encode($this->queryBuilder->getRequest(), JSON_THROW_ON_ERROR));
        if ($cacheResult === true && isset(self::$runtimeQueryResultCache[$queryHash])) {
            return self::$runtimeQueryResultCache[$queryHash];
        }
        $queryResult = new ElasticSearchQueryResult($this);
        self::$runtimeQueryResultCache[$queryHash] = $queryResult;

        return $queryResult;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return $this->queryBuilder->getTotalItems();
    }

    /**
     * @param int|null $limit
     * @return QueryInterface
     * @throws IllegalObjectTypeException
     */
    public function setLimit(?int $limit): QueryInterface
    {
        if ($limit < 1 || !is_int($limit)) {
            throw new InvalidArgumentException('Expecting integer greater than zero for limit');
        }

        $this->queryBuilder->limit($limit);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getLimit(): int
    {
        return $this->queryBuilder->getLimit();
    }

    /**
     * {@inheritdoc}
     */
    public function setOffset(?int $offset): QueryInterface
    {
        if ($offset < 1 || !is_int($offset)) {
            throw new InvalidArgumentException('Expecting integer greater than zero for offset', 1605474906);
        }

        $this->queryBuilder->from($offset);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOffset(): int
    {
        return $this->queryBuilder->getFrom();
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return Node::class;
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function setOrderings(array $orderings): QueryInterface
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749035);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function getOrderings(): array
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749036);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function matching($constraint): QueryInterface
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749037);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function getConstraint()
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749038);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function logicalAnd(mixed $constraint1, mixed ...$constraints)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749039);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function logicalOr(mixed $constraint1, mixed ...$constraints)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749040);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function logicalNot($constraint)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749041);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function equals($propertyName, $operand, $caseSensitive = true)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749042);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function like($propertyName, $operand, $caseSensitive = true)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749043);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function contains($propertyName, $operand)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749044);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function isEmpty($propertyName)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749045);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function in($propertyName, $operand)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749046);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function lessThan($propertyName, $operand)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749047);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function lessThanOrEqual($propertyName, $operand)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749048);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function greaterThan($propertyName, $operand)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749049);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function greaterThanOrEqual($propertyName, $operand)
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749050);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function setDistinct(bool $distinct = true): QueryInterface
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749051);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function isDistinct(): bool
    {
        throw new Exception(__FUNCTION__ . ' not implemented', 1421749052);
    }

    /**
     * @return ElasticSearchQueryBuilder
     */
    public function getQueryBuilder(): ElasticSearchQueryBuilder
    {
        return $this->queryBuilder;
    }
}
