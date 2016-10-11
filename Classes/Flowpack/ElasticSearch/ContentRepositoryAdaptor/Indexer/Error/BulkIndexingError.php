<?php
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
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Exception;

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
     * @throws Exception
     */
    public function __construct(array $currentBulkRequest, array $errors)
    {
        $this->currentBulkRequest = $currentBulkRequest;
        $this->errors = json_decode($errors, true);

        if (!file_exists(FLOW_PATH_DATA . 'Logs/ElasticSearch')) {
            mkdir(FLOW_PATH_DATA . 'Logs/ElasticSearch');
        }

        $referenceCode = date('YmdHis', $_SERVER['REQUEST_TIME']) . substr(md5(rand()), 0, 6);

        $this->filename = FLOW_PATH_DATA . 'Logs/ElasticSearch/' . $referenceCode . '.txt';
        $this->message = sprintf('Bulk indexing errors detected - See also: Data/Logs/ElasticSearch/%s on host: %s', basename($this->filename), gethostname());
    }

    /**
     * Log the error message
     *
     * @return void
     */
    public function log()
    {
        if (file_exists(FLOW_PATH_DATA . 'Logs/ElasticSearch') && is_dir(FLOW_PATH_DATA . 'Logs/ElasticSearch') && is_writable(FLOW_PATH_DATA . 'Logs/ElasticSearch')) {
            file_put_contents($this->filename, $this->renderErrors());
            $this->logger->log($this->message, LOG_ERR, [], 'Flowpack.ElasticSearch.ContentRepositoryAdaptor', __CLASS__, __FUNCTION__);
        } else {
            $this->logger->log(sprintf('Could not write indexing errors backtrace into %s because the directory could not be created or is not writable.', FLOW_PATH_DATA . 'Logs/ElasticSearch/'), LOG_WARNING, [], 'Flowpack.ElasticSearch.ContentRepositoryAdaptor', __CLASS__, __FUNCTION__);
        }
    }

    /**
     * @return string
     */
    public function message()
    {
        return $this->message;
    }


    /**
     * @return string
     */
    protected function renderErrors()
    {
        $bulkRequest = json_encode($this->currentBulkRequest, JSON_PRETTY_PRINT);
        $errors = json_encode($this->errors, JSON_PRETTY_PRINT);
        return sprintf("Payload:\n========\n\n%s\n\nErrors:\n=======\n\n%s\n\n", $bulkRequest, $errors);
    }
}
