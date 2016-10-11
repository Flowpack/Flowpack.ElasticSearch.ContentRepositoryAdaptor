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

use TYPO3\Flow\Annotations as Flow;

/**
 * Error Interface
 */
interface ErrorInterface
{
    /**
     * Log the error message
     *
     * @return void
     */
    public function log();

    /**
     * Get a short log message for reporting
     *
     * @return string
     */
    public function message();
}
