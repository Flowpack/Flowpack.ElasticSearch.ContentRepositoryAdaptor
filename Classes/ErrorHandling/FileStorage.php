<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\ErrorHandling;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\RuntimeException;

/**
 * Handle error result and build human readable output for analysis
 */
class FileStorage implements ErrorStorageInterface
{
    public function __construct()
    {
        if (!file_exists(FLOW_PATH_DATA . 'Logs/Elasticsearch')) {
            mkdir(FLOW_PATH_DATA . 'Logs/Elasticsearch');
        }
    }

    /**
     * Log the error message
     *
     * @param array $errorResult
     * @return string
     * @throws RuntimeException
     */
    public function logErrorResult(array $errorResult): string
    {
        $referenceCode = date('YmdHis', $_SERVER['REQUEST_TIME']) . substr(md5((string)mt_rand()), 0, 6);

        $filename = FLOW_PATH_DATA . 'Logs/Elasticsearch/' . $referenceCode . '.txt';
        $message = sprintf('Elasticsearch API Error detected - See also: Data/Logs/Elasticsearch/%s on host: %s', basename($filename), gethostname());

        if (file_exists(FLOW_PATH_DATA . 'Logs/Elasticsearch') && is_dir(FLOW_PATH_DATA . 'Logs/Elasticsearch') && is_writable(FLOW_PATH_DATA . 'Logs/Elasticsearch')) {
            file_put_contents($filename, $this->renderErrorResult($errorResult));
        } else {
            throw new RuntimeException('Elasticsearch error response could not be written to ' . $filename, 1588835331);
        }

        return $message;
    }

    /**
     * @param array $errorResult
     * @return string
     */
    protected function renderErrorResult(array $errorResult): string
    {
        $error = json_encode($errorResult, JSON_PRETTY_PRINT);
        return sprintf("Error:\n=======\n\n%s\n\n", $error);
    }
}
