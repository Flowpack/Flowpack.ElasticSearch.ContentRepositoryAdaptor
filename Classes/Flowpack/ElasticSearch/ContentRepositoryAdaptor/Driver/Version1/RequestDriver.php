<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version1;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\AbstractDriver;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\RequestDriverInterface;
use Flowpack\ElasticSearch\Domain\Model\Index;
use TYPO3\Flow\Annotations as Flow;

/**
 * Request driver for Elasticsearch version 1.x
 *
 * @Flow\Scope("singleton")
 */
class RequestDriver extends AbstractDriver implements RequestDriverInterface
{
    /**
     * {@inheritdoc}
     */
    public function bulk(Index $index, $request)
    {
        if (is_array($request)) {
            $request = json_encode($request);
        }

        // Bulk request MUST end with line return
        $request = trim($request) . "\n";

        $response = $index->request('POST', '/_bulk', [], $request)->getOriginalResponse()->getContent();

        return array_map(function ($line) {
            return json_decode($line);
        }, explode("\n", $response));
    }
}
