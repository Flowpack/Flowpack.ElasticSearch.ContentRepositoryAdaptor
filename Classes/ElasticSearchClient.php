<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\ConfigurationException;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\IndexNameStrategyInterface;
use Flowpack\ElasticSearch\Domain\Model\Client;
use Flowpack\ElasticSearch\Domain\Model\Index;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\Flow\Annotations as Flow;

/**
 * The elasticsearch client to be used by the content repository adapter.
 *
 * Used to:
 *
 * - make the ElasticSearch Client globally available
 * - allow to access the index to be used for reading/writing in a global way
 *
 * @Flow\Scope("singleton")
 */
class ElasticSearchClient extends Client
{
    /**
     * @var IndexNameStrategyInterface
     * @Flow\Inject
     */
    protected $indexNameStrategy;

    /**
     * @var string
     */
    protected $dimensionsHash;

    /**
     * @var Node
     */
    protected $contextNode;

    /**
     * @return Node
     */
    public function getContextNode(): Node
    {
        return $this->contextNode;
    }

    /**
     * @param Node $contextNode
     */
    public function setContextNode(Node $contextNode): void
    {
        $this->setDimensions($contextNode->dimensionSpacePoint);
        $this->contextNode = $contextNode;
    }

    /**
     * @param array $dimensionSpacePoint
     */
    public function setDimensions(DimensionSpacePoint $dimensionSpacePoint): void
    {
        $this->dimensionsHash = $dimensionSpacePoint->hash;
    }

    /**
     * @return string
     */
    public function getDimensionsHash(): string
    {
        return $this->dimensionsHash;
    }

    /**
     * @param \Closure $closure
     * @param array $dimensionValues
     * @throws \Exception
     */
    public function withDimensions(\Closure $closure, DimensionSpacePoint $dimensionSpacePoint): void
    {
        $previousDimensionHash = $this->dimensionsHash;
        try {
            $this->setDimensions($dimensionSpacePoint);
            $closure();
        } finally {
            $this->dimensionsHash = $previousDimensionHash;
        }
    }

    /**
     * Get the index name to be used
     *
     * @return string
     * @throws Exception
     * @throws ConfigurationException
     * @todo Add a contraints, if the system use content dimensions, the dimensionsHash MUST be set
     */
    public function getIndexName(): string
    {
        $name = $this->getIndexNamePrefix();
        if ($this->dimensionsHash !== null) {
            $name .= '-' . $this->dimensionsHash;
        }
        return $name;
    }

    /**
     * @return string
     * @throws Exception
     * @throws ConfigurationException
     */
    public function getIndexNamePrefix(): string
    {
        $name = trim($this->indexNameStrategy->get());
        if ($name === '') {
            throw new ConfigurationException('IndexNameStrategy ' . get_class($this->indexNameStrategy) . ' returned an empty index name', 1582538800);
        }

        return $name;
    }

    /**
     * Retrieve the index to be used for querying or on-the-fly indexing.
     * In Elasticsearch, this index is an *alias* to the currently used index.
     *
     * @return \Flowpack\ElasticSearch\Domain\Model\Index
     * @throws Exception
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws ConfigurationException
     */
    public function getIndex(): Index
    {
        return $this->findIndex($this->getIndexName());
    }
}
