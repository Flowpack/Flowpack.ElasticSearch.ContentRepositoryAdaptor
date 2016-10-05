<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service;

/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch.ContentRepositoryAdaptor". *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\Error\ErrorInterface;
use TYPO3\Flow\Annotations as Flow;

/**
 * Error Handling Service
 *
 * @Flow\Scope("singleton")
 */
class ErrorHandlingService implements \IteratorAggregate
{
    protected $errors = [];

    /**
     * @param ErrorInterface $error
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
