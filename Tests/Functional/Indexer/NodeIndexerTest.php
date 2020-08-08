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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\NodeTypeMappingBuilderInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\QueryInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version6\Mapping\NodeTypeMappingBuilder;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version6\Query\FilteredQuery;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\ConfigurationException;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\DimensionsService;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional\TRaits\ContentRepositoryNodeCreationTrait;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Flowpack\ElasticSearch\Exception;
use Flowpack\ElasticSearch\Transfer\Exception\ApiException;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\Utility\Arrays;

class NodeIndexerTest extends FunctionalTestCase
{
    use ContentRepositoryNodeCreationTrait;

    const TESTING_INDEX_PREFIX = 'neoscr_testing';

    /**
     * @var boolean
     */
    protected static $testablePersistenceEnabled = true;

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

    /**
     * @var NodeTypeMappingBuilder
     */
    protected $nodeTypeMappingBuilder;

    public function setUp(): void
    {
        parent::setUp();
        $this->searchClient = $this->objectManager->get(ElasticSearchClient::class);
        $this->nodeIndexer = $this->objectManager->get(NodeIndexer::class);
        $this->dimensionService = $this->objectManager->get(DimensionsService::class);
        $this->nodeTypeMappingBuilder = $this->objectManager->get(NodeTypeMappingBuilderInterface::class);
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
        static::assertEquals(self::TESTING_INDEX_PREFIX . '-default', $index->getName());
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

        static::assertEquals(self::TESTING_INDEX_PREFIX . '-' . $dimesionHash, $index->getName());
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
     * @test
     */
    public function indexAndDeleteNode(): void
    {
        $this->setupContentRepository();
        $this->createNodesForNodeSearchTest();
        /** @var NodeInterface $testNode */
        $testNode = current($this->siteNode->getChildNodes('Neos.NodeTypes:Page', 1));

        $dimensionValues = ['language' => ['en_US']];
        $this->nodeIndexer->setDimensions($dimensionValues);
        $this->nodeIndexer->getIndex()->create();

        $nodeTypeMappingCollection = $this->nodeTypeMappingBuilder->buildMappingInformation($this->nodeIndexer->getIndex());
        foreach ($nodeTypeMappingCollection as $mapping) {
            /** @var Mapping $mapping */
            $mapping->apply();
        }

        $this->nodeIndexer->indexNode($testNode);
        $this->nodeIndexer->flush();
        $this->assertTrue($this->nodeExistsInIndex($testNode), 'Node was not successfully indexed.');

        $this->nodeIndexer->removeNode($testNode);
        $this->nodeIndexer->flush();
        usleep(500000);
        $this->assertFalse($this->nodeExistsInIndex($testNode), 'Node still exists after delete');
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

    /**
     * @param NodeInterface $testNode
     * @return bool
     * @throws ConfigurationException
     * @throws Exception
     * @throws \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception
     * @throws \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\QueryBuildingException
     * @throws \Neos\Flow\Http\Exception
     */
    private function nodeExistsInIndex(NodeInterface $testNode): bool
    {
        $this->searchClient->setContextNode($this->siteNode);
        /** @var FilteredQuery $query */
        $query = $this->objectManager->get(QueryInterface::class);
        $query->queryFilter('term', ['neos_node_identifier' => $testNode->getIdentifier()]);

        $result = $this->nodeIndexer->getIndex()->request('GET', '/_search', [], $query->toArray())->getTreatedContent();
        return count(Arrays::getValueByPath($result, 'hits.hits')) === 1;
    }
}
