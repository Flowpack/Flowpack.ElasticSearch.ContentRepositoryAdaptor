<?php
declare(strict_types=1);

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer;

use Generator;

class BulkRequestPart
{
    /**
     * @var string
     */
    protected $targetDimensionsHash;

    /**
     * JSON Payload of the current requests
     * @var string
     */
    protected $requests = [];

    /**
     * Size in octet of the current request
     * @var int
     */
    protected $size = 0;

    public function __construct(string $targetDimensionsHash, array $requests)
    {
        $this->targetDimensionsHash = $targetDimensionsHash;
        $this->requests = array_map(function (array $request) {
            $data = json_encode($request);
            if ($data === false) {
                return null;
            }
            $this->size += strlen($data);
            return $data;
        }, $requests);
    }

    public function getTargetDimensionsHash(): string
    {
        return $this->targetDimensionsHash;
    }

    public function getRequest(): Generator
    {
        foreach ($this->requests as $request) {
            yield $request;
        }
    }

    public function getSize(): int
    {
        return $this->size;
    }
}
