<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\Error\ErrorInterface;
use TYPO3\Flow\Annotations as Flow;

/**
 * Error Handling Service
 *
 * @Flow\Scope("singleton")
 */
class ErrorHandlingService implements \IteratorAggregate
{
    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @param ErrorInterface $error
     * @return void
     */
    public function log(ErrorInterface $error)
    {
        $this->errors[] = $error;
        $error->log();
    }

    /**
     * @return bool
     */
    public function hasError()
    {
        return count($this->errors) > 0;
    }

    /**
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->errors);
    }
}
