<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\LoggerInterface;
use TYPO3\Flow\Annotations as Flow;

/**
 * Abstract Elasticsearch driver
 */
abstract class AbstractDriver
{
    /**
     * @Flow\Inject
     * @var ElasticSearchClient
     */
    protected $searchClient;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;
}
