<?php

declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version5;

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
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\AbstractDriver;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\RequestDriverInterface;
use Flowpack\ElasticSearch\Domain\Model\Index;

/**
 * Request driver for Elasticsearch version 5.x
 *
 * @Flow\Scope("singleton")
 */
class RequestDriver extends AbstractDriver implements RequestDriverInterface
{
    /**
     * {@inheritdoc}
     * @throws \Flowpack\ElasticSearch\Exception
     */
    public function bulk(Index $index, $request): array
    {
        if (is_array($request)) {
            $request = json_encode($request);
        }

        // Bulk request MUST end with line return
        $request = trim($request) . "\n";

        $response = $index->request('POST', '/_bulk', [], $request)->getOriginalResponse()->getContent();

        return array_map('json_decode', explode("\n", $response));
    }
}
