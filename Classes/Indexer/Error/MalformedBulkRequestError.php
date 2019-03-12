<?php

declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\Error;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\LoggerInterface;
use Neos\Flow\Annotations as Flow;

/**
 * Handle malformed bulk request error
 */
class MalformedBulkRequestError implements ErrorInterface
{
    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var array
     */
    protected $tuple;

    /**
     * @param string $message
     * @param array $tuple
     */
    public function __construct($message, array $tuple)
    {
        $this->message = $message;
        $this->tuple = $tuple;
    }

    /**
     * Log the error message
     *
     * @return void
     */
    public function log(): void
    {
        $this->logger->log($this->message(), LOG_ERR, $this->tuple);
    }

    /**
     * Get a short log message for reporting
     *
     * @return string
     */
    public function message(): string
    {
        return $this->message;
    }
}
