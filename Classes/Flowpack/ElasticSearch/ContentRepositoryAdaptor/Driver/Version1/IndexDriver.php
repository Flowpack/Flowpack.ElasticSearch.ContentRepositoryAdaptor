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
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\DriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\IndexDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use TYPO3\Flow\Annotations as Flow;

/**
 * Index Management driver for Elasticsearch version 1.x
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
     */
    public function deleteIndex($index)
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
     */
    public function indexesByAlias($alias)
    {
        $response = $this->searchClient->request('GET', '/_alias/' . $alias);
        if ($response->getStatusCode() !== 200) {
            throw new Exception('The alias "' . $alias . '" was not found with some unexpected error... (return code: ' . $response->getStatusCode() . ')', 1383650137);
        }

        return array_keys($response->getTreatedContent());
    }
}
