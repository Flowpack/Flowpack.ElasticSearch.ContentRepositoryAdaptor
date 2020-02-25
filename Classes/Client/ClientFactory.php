<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Client;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Flowpack\ElasticSearch\Domain\Model\Client;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class ClientFactory
{
    /**
     * @Flow\Inject
     * @var \Flowpack\ElasticSearch\Domain\Factory\ClientFactory
     */
    protected $clientFactory;

    /**
     * Create a client
     *
     * @return Client
     * @throws \Flowpack\ElasticSearch\Exception
     */
    public function create(): Client
    {
        return $this->clientFactory->create(null, ElasticSearchClient::class);
    }
}
