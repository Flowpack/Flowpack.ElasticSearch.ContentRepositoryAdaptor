<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Command\NodeIndexCommandController;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryBuilder;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Neos\Flow\Tests\FunctionalTestCase;

abstract class BaseElasticsearchContentRepositoryAdapterTest extends FunctionalTestCase
{
    protected const TESTING_INDEX_PREFIX = 'neoscr_testing';

    /**
     * @var boolean
     */
    protected static $testablePersistenceEnabled = true;

    /**
     * @var NodeIndexCommandController
     */
    protected $nodeIndexCommandController;

    /**
     * @var ElasticSearchClient
     */
    protected $searchClient;

    protected static $instantiatedIndexes = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->nodeIndexCommandController = $this->objectManager->get(NodeIndexCommandController::class);
        $this->searchClient = $this->objectManager->get(ElasticSearchClient::class);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->inject($this->contextFactory, 'contextInstances', []);

        if (!$this->isIndexInitialized()) {
            // clean up any existing indices
            $this->searchClient->request('DELETE', '/' . self::TESTING_INDEX_PREFIX . '*');
        }
    }

    /**
     * @param string $method
     * @return string
     */
    protected function getLogMessagePrefix(string $method): string
    {
        return substr(strrchr($method, '\\'), 1);
    }

    protected function indexNodes(): void
    {
        if ($this->isIndexInitialized()) {
            return;
        }

        $this->nodeIndexCommandController->buildCommand(null, false, null, 'functionaltest');
        $this->setIndexInitialized();
    }

    /**
     * @return ElasticSearchQueryBuilder
     */
    protected function getQueryBuilder(): ElasticSearchQueryBuilder
    {
        try {
            /** @var ElasticSearchQueryBuilder $elasticSearchQueryBuilder */
            $elasticSearchQueryBuilder = $this->objectManager->get(ElasticSearchQueryBuilder::class);
            $this->inject($elasticSearchQueryBuilder, 'now', new \DateTimeImmutable('@1735685400')); // Dec. 31, 2024 23:50:00

            return $elasticSearchQueryBuilder;
        } catch (\Exception $exception) {
            static::fail('Setting up the QueryBuilder failed: ' . $exception->getMessage());
        }
    }

    protected function isIndexInitialized(): bool
    {
        return self::$instantiatedIndexes[get_class($this)] ?? false;
    }

    protected function setIndexInitialized(): void
    {
        self::$instantiatedIndexes[get_class($this)] = true;
    }
}
