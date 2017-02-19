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

use TYPO3\Eel\ProtectedContextAwareInterface;
use TYPO3\Flow\Persistence\QueryResultInterface;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

class ElasticSearchQueryResult implements QueryResultInterface, ProtectedContextAwareInterface
{
    /**
     * @var ElasticSearchQuery
     */
    protected $elasticSearchQuery;

    /**
     * @var array
     */
    protected $result = null;

    /**
     * @var array
     */
    protected $nodes = null;

    /**
     * @var integer
     */
    protected $count = null;

    public function __construct(ElasticSearchQuery $elasticSearchQuery)
    {
        $this->elasticSearchQuery = $elasticSearchQuery;
    }

    /**
     * Initialize the results by really executing the query
     */
    protected function initialize()
    {
        if ($this->result === null) {
            $queryBuilder = $this->elasticSearchQuery->getQueryBuilder();
            $this->result = $queryBuilder->fetch();
            $this->nodes = $this->result['nodes'];
            $this->count = $queryBuilder->getTotalItems();
        }
    }

    /**
     * @return ElasticSearchQuery
     */
    public function getQuery()
    {
        return clone $this->elasticSearchQuery;
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        $this->initialize();

        return current($this->nodes);
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->initialize();

        return next($this->nodes);
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        $this->initialize();

        return key($this->nodes);
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        $this->initialize();

        return current($this->nodes) !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->initialize();
        reset($this->nodes);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        $this->initialize();

        return isset($this->nodes[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        $this->initialize();

        return $this->nodes[$offset];
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        $this->initialize();
        $this->nodes[$offset] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        $this->initialize();
        unset($this->nodes[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function getFirst()
    {
        $this->initialize();
        if (count($this->nodes) > 0) {
            return array_values($this->nodes)[0];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        $this->initialize();

        return $this->nodes;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        if ($this->count === null) {
            $this->count = $this->elasticSearchQuery->getQueryBuilder()->count();
        }

        return $this->count;
    }

    /**
     * @return integer the current number of results which can be iterated upon
     * @api
     */
    public function getAccessibleCount()
    {
        $this->initialize();

        return count($this->nodes);
    }

    /**
     * @return array
     */
    public function getAggregations()
    {
        $this->initialize();
        if (array_key_exists('aggregations', $this->result)) {
            return $this->result['aggregations'];
        }

        return [];
    }

    /**
     * Returns an array of type
     * [
     *     <suggestionName> => [
     *         'text' => <term>
     *         'options' => [
     *              [
     *               'text' => <suggestionText>
     *               'score' => <score>
     *              ],
     *              [
     *              ...
     *              ]
     *         ]
     *     ]
     * ]
     *
     * @return array
     */
    public function getSuggestions()
    {
        $this->initialize();
        if (array_key_exists('suggest', $this->result)) {
            return $this->result['suggest'];
        }

        return [];
    }

    /**
     * Returns the ElasticSearch "hit" (e.g. the raw content being transferred back from ElasticSearch)
     * for the given node.
     *
     * Can be used for example to access highlighting information.
     *
     * @param NodeInterface $node
     * @return array the ElasticSearch hit, or NULL if it does not exist.
     * @api
     */
    public function searchHitForNode(NodeInterface $node)
    {
        return $this->elasticSearchQuery->getQueryBuilder()->getFullElasticSearchHitForNode($node);
    }

    /**
     * Returns the array with all sort values for a given node. The values are fetched from the raw content
     * ElasticSearch returns within the hit data
     *
     * @param NodeInterface $node
     * @return array
     */
    public function getSortValuesForNode(NodeInterface $node)
    {
        $hit = $this->searchHitForNode($node);
        if (is_array($hit) && array_key_exists('sort', $hit)) {
            return $hit['sort'];
        }

        return [];
    }

    /**
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
