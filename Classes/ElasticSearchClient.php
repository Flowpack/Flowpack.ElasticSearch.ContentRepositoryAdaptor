<?php

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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\DimensionsService;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\IndexNameStrategy;
use Flowpack\ElasticSearch\Domain\Model\Client;
use Neos\Flow\Annotations as Flow;

/**
 * The elasticsearch client to be used by the content repository adapter. Singleton, can be injected.
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
     * @var IndexNameStrategy
     * @Flow\Inject
     */
    protected $indexNameStrategy;

    /**
     * @var DimensionsService
     * @Flow\Inject
     */
    protected $dimensionsService;

    /**
     * @var string
     */
    protected $dimensionsHash;

    /**
     * @param array $dimensionValues
     */
    public function setDimensions(array $dimensionValues = [])
    {
        $this->dimensionsHash = $this->dimensionsService->hash($dimensionValues);
    }

    /**
     * @return string
     */
    public function getDimensionsHash()
    {
        return $this->dimensionsHash;
    }

    /**
     * @param \Closure $closure
     * @param array $dimensionValues
     * @throws \Exception
     */
    public function withDimensions(\Closure $closure, array $dimensionValues = [])
    {
        $previousDimensionHash = $this->dimensionsHash;
        try {
            $this->setDimensions($dimensionValues);
            $closure();
            $this->dimensionsHash = $previousDimensionHash;
        } catch (\Exception $exception) {
            $this->dimensionsHash = $previousDimensionHash;
            throw $exception;
        }
    }

    /**
     * Get the index name to be used
     *
     * @return string
     * @throws Exception
     */
    public function getIndexName()
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
     */
    public function getIndexNamePrefix()
    {
        $name = trim($this->indexNameStrategy->get());
        if ($name === '') {
            throw new Exception('Index name can not be null');
        }

        return $name;
    }

    /**
     * Retrieve the index to be used for querying or on-the-fly indexing.
     * In Elasticsearch, this index is an *alias* to the currently used index.
     *
     * @return \Flowpack\ElasticSearch\Domain\Model\Index
     * @throws Exception
     */
    public function getIndex()
    {
        return $this->findIndex($this->getIndexName());
    }
}
