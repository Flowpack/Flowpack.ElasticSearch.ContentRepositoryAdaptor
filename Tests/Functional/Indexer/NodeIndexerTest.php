<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional\Indexer;

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
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\ConfigurationException;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\DimensionsService;
use Flowpack\ElasticSearch\Exception;
use Flowpack\ElasticSearch\Transfer\Exception\ApiException;
use Neos\Flow\Tests\FunctionalTestCase;

class NodeIndexerTest extends FunctionalTestCase
{
    const TESTING_INDEX_PREFIX = 'neoscr_testing';

    /**
     * @var NodeIndexer
     */
    protected $nodeIndexer;

    /**
     * @var DimensionsService
     */
    protected $dimensionService;

    /**
     * @var ElasticSearchClient
     */
    protected $searchClient;

    public function setUp(): void
    {
        parent::setUp();
        $this->searchClient = $this->objectManager->get(ElasticSearchClient::class);
        $this->nodeIndexer = $this->objectManager->get(NodeIndexer::class);
        $this->dimensionService = $this->objectManager->get(DimensionsService::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->searchClient->request('DELETE', '/' . self::TESTING_INDEX_PREFIX . '*');
    }

    /**
     * @test
     */
    public function getIndexWithoutDimensionConfigured(): void
    {
        $this->nodeIndexer->setDimensions([]);
        $index = $this->nodeIndexer->getIndex();
        static::assertEquals(self::TESTING_INDEX_PREFIX . '-default-functionaltest', $index->getName());
    }

    /**
     * @test
     *
     * @throws \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception
     * @throws ConfigurationException
     * @throws Exception
     */
    public function getIndexForDimensionConfiguration(): void
    {
        $dimensionValues = ['language' => ['de']];
        $this->nodeIndexer->setDimensions($dimensionValues);
        $index = $this->nodeIndexer->getIndex();
        $dimesionHash = $this->dimensionService->hash($dimensionValues);

        static::assertEquals(self::TESTING_INDEX_PREFIX . '-' . $dimesionHash . '-functionaltest', $index->getName());
    }

    /**
     * @test
     */
    public function updateIndexAlias(): void
    {
        $dimensionValues = ['language' => ['de']];
        $this->nodeIndexer->setDimensions($dimensionValues);
        $this->nodeIndexer->setIndexNamePostfix((string)time());
        $this->nodeIndexer->getIndex()->create();

        $this->assertIndexExists($this->nodeIndexer->getIndexName());
        $this->nodeIndexer->updateIndexAlias();

        $this->assertAliasesEquals(self::TESTING_INDEX_PREFIX, [$this->nodeIndexer->getIndexName()]);
    }


    /**
     * @param string $indexName
     * @throws \Flowpack\ElasticSearch\Transfer\Exception
     * @throws ApiException
     * @throws \Neos\Flow\Http\Exception
     */
    protected function assertIndexExists(string $indexName): void
    {
        $response = $this->searchClient->request('HEAD', '/' . $indexName);
        self::assertEquals(200, $response->getStatusCode());
    }

    protected function assertAliasesEquals(string $aliasPrefix, array $expectdAliases): void
    {
        $content = $this->searchClient->request('GET', '/_alias/' . $aliasPrefix . '*')->getTreatedContent();
        static::assertEquals($expectdAliases, array_keys($content));
    }
}
