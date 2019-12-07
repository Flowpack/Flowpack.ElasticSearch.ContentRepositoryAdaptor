<?php

declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Factory;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\DocumentDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\IndexDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\IndexerDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\RequestDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\SystemDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\DriverConfigurationException;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class DriverFactory extends AbstractDriverSpecificObjectFactory
{
    /**
     * @return DocumentDriverInterface
     * @throws DriverConfigurationException
     */
    public function createDocumentDriver(): DocumentDriverInterface
    {
        return $this->resolve('document');
    }

    /**
     * @return IndexerDriverInterface
     * @throws DriverConfigurationException
     */
    public function createIndexerDriver(): IndexerDriverInterface
    {
        return $this->resolve('indexer');
    }

    /**
     * @return IndexDriverInterface
     * @throws DriverConfigurationException
     */
    public function createIndexManagementDriver(): IndexDriverInterface
    {
        return $this->resolve('indexManagement');
    }

    /**
     * @return RequestDriverInterface
     * @throws DriverConfigurationException
     */
    public function createRequestDriver(): RequestDriverInterface
    {
        return $this->resolve('request');
    }

    /**
     * @return SystemDriverInterface
     * @throws DriverConfigurationException
     */
    public function createSystemDriver(): SystemDriverInterface
    {
        return $this->resolve('system');
    }
}
