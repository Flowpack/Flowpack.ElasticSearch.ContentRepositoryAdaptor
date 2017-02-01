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
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\IndexManagementDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use TYPO3\Flow\Annotations as Flow;

/**
 * Index Management Driver for Elastic version 1.x
 *
 * @Flow\Scope("singleton")
 */
class IndexManagementDriver extends AbstractDriver implements IndexManagementDriverInterface
{
    /**
     * {@inheritdoc}
     */
    public function delete(array $indices)
    {
        if (count($indices) === 0) {
            return;
        }
        $this->searchClient->request('DELETE', '/' . implode(',', $indices) . '/');
    }

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
    public function remove($aliasName)
    {
        $response = $this->searchClient->request('HEAD', '/' . $aliasName);
        if ($response->getStatusCode() === 200) {
            $response = $this->searchClient->request('DELETE', '/' . $aliasName);
            if ($response->getStatusCode() !== 200) {
                throw new Exception('The index "' . $aliasName . '" could not be removed to be replaced by an alias. (return code: ' . $response->getStatusCode() . ')', 1395419177);
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
