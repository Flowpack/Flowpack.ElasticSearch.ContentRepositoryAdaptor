<?php
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
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\DriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\IndexerDriverInterface;
use TYPO3\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class DriverFactory extends AbstractDriverSpecificObjectFactory
{
    /**
     * @return DocumentDriverInterface
     */
    public function createDocumentDriver()
    {
        return $this->resolve('document');
    }

    /**
     * @return IndexerDriverInterface
     */
    public function createIndexerDriver()
    {
        return $this->resolve('indexer');
    }

    /**
     * @return IndexerDriverInterface
     */
    public function createIndexManagementDriver()
    {
        return $this->resolve('indexManagement');
    }

    /**
     * @return IndexerDriverInterface
     */
    public function createRequestDriver()
    {
        return $this->resolve('request');
    }

    /**
     * @return IndexerDriverInterface
     */
    public function createSystemDriver()
    {
        return $this->resolve('system');
    }
}
