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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\AbstractDriver;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\IndexDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use Neos\Flow\Annotations as Flow;

/**
 * Index management driver for Elasticsearch version 5.x
 *
 * @Flow\Scope("singleton")
 */
class IndexDriver extends AbstractDriver implements IndexDriverInterface
{
    /**
     * {@inheritdoc}
     */
    public function aliasActions(array $actions)
    {
        $this->searchClient->request('POST', '/_aliases', [], \json_encode(['actions' => $actions]));
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function deleteIndex(string $index): void
    {
        $response = $this->searchClient->request('HEAD', '/' . $index);
        if ($response->getStatusCode() === 200) {
            $response = $this->searchClient->request('DELETE', '/' . $index);
            if ($response->getStatusCode() !== 200) {
                throw new Exception('The index "' . $index . '" could not be deleted. (return code: ' . $response->getStatusCode() . ')', 1395419177);
            }
        }
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function indexesByAlias(string $alias): array
    {
        $response = $this->searchClient->request('GET', '/_alias/' . $alias);
        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200 && $statusCode !== 404) {
            throw new Exception('The alias "' . $alias . '" was not found with some unexpected error... (return code: ' . $statusCode . ')', 1383650137);
        }

        // return empty array if content from response cannot be read as an array
        $treatedContent = $response->getTreatedContent();

        return is_array($treatedContent) ? array_keys($treatedContent) : [];
    }

    /**
     * {@inheritdoc}
     */
    public function indexesByPrefix(string $prefix): array
    {
        $response = $this->searchClient->request('GET', '/_alias/');

        // return empty array if content from response cannot be read as an array
        $treatedContent = $response->getTreatedContent();
        if (!\is_array($treatedContent)) {
            return [];
        }
        return \array_filter(\array_keys($treatedContent), function ($indexName) use ($prefix) {
            $prefix = $prefix . '-';
            return substr($indexName, 0, strlen($prefix)) === $prefix;
        });
    }
}
