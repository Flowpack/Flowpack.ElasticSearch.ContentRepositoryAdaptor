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

use Neos\Flow\Log\Utility\LogEnvironment;
use Psr\Log\LoggerInterface;
use Neos\Flow\Annotations as Flow;

/**
 * Handle Bulk Indexing Error and build human readable output for analysis
 */
class BulkIndexingError implements ErrorInterface
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
     * @var string
     */
    protected $filename;

    /**
     * @var array
     */
    protected $currentBulkRequest;

    /**
     * @var array
     */
    protected $errors;

    /**
     * @param array $currentBulkRequest
     * @param array $errors
     */
    public function __construct(array $currentBulkRequest, array $errors)
    {
        $this->currentBulkRequest = $currentBulkRequest;
        $this->errors = json_decode($errors, true);

        if (!file_exists(FLOW_PATH_DATA . 'Logs/Elasticsearch')) {
            mkdir(FLOW_PATH_DATA . 'Logs/Elasticsearch');
        }

        $referenceCode = date('YmdHis', $_SERVER['REQUEST_TIME']) . substr(md5(rand()), 0, 6);

        $this->filename = FLOW_PATH_DATA . 'Logs/Elasticsearch/' . $referenceCode . '.txt';
        $this->message = sprintf('Bulk indexing errors detected - See also: Data/Logs/Elasticsearch/%s on host: %s', basename($this->filename), gethostname());
    }

    /**
     * Log the error message
     *
     * @return void
     */
    public function log(): void
    {
        if (file_exists(FLOW_PATH_DATA . 'Logs/Elasticsearch') && is_dir(FLOW_PATH_DATA . 'Logs/Elasticsearch') && is_writable(FLOW_PATH_DATA . 'Logs/Elasticsearch')) {
            file_put_contents($this->filename, $this->renderErrors());
            $this->logger->error($this->message, LogEnvironment::fromMethodName(__METHOD__));
        } else {
            $this->logger->warning(sprintf('Could not write indexing errors backtrace into %s because the directory could not be created or is not writable.', FLOW_PATH_DATA . 'Logs/Elasticsearch/'), LogEnvironment::fromMethodName(__METHOD__));
        }
    }

    /**
     * @return string
     */
    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return string
     */
    protected function renderErrors(): string
    {
        $bulkRequest = json_encode($this->currentBulkRequest, JSON_PRETTY_PRINT);
        $errors = json_encode($this->errors, JSON_PRETTY_PRINT);

        return sprintf("Payload:\n========\n\n%s\n\nErrors:\n=======\n\n%s\n\n", $bulkRequest, $errors);
    }
}
