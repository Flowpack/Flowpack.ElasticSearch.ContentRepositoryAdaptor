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

use Neos\Flow\Annotations as Flow;
use Psr\Log\LoggerInterface;

/**
 * Error Handling Service
 *
 * @Flow\Scope("singleton")
 */
class ErrorHandlingService
{
    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var int
     */
    protected $errorCount = 0;

    /**
     * @param string $message
     * @param $context
     */
    public function log(string $message, $context): void
    {
        $this->errorCount++;
        $this->logger->error($message, $context);
    }

    /**
     * @return int
     */
    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    /**
     * @return bool
     */
    public function hasError(): bool
    {
        return $this->errorCount > 0;
    }
}
