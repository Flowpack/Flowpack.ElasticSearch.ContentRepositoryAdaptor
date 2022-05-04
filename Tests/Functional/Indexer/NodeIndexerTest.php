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
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional\BaseElasticsearchContentRepositoryAdapterTest;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional\Traits\Assertions;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional\Traits\ContentRepositoryNodeCreationTrait;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional\Traits\ContentRepositorySetupTrait;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Flowpack\ElasticSearch\Exception;
use Flowpack\ElasticSearch\Transfer\Exception\ApiException;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\Utility\Arrays;

class NodeIndexerTest extends BaseElasticsearchContentRepositoryAdapterTest
{
    use ContentRepositorySetupTrait, ContentRepositoryNodeCreationTrait, Assertions;

    /**
     * @var NodeIndexer
     */
    protected $nodeIndexer;

    /**
     * @var DimensionsService
     */
    protected $dimensionService;

    /**
     * @var NodeTypeMappingBuilder
     */
    protected $nodeTypeMappingBuilder;

    public function setUp(): void
    {
        parent::setUp();
        $this->nodeIndexer = $this->objectManager->get(NodeIndexer::class);
        $this->dimensionService = $this->objectManager->get(DimensionsService::class);
        $this->nodeTypeMappingBuilder = $this->objectManager->get(NodeTypeMappingBuilderInterface::class);
    }

    /**
     * @test
     */
    public function getIndexWithoutDimensionConfigured(): void
    {
        $this->nodeIndexer->setIndexNamePostfix('');
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
        $testNode = $this->setupCrAndIndexTestNode();
        self::assertTrue($this->nodeExistsInIndex($testNode), 'Node was not successfully indexed.');

        $this->nodeIndexer->removeNode($testNode);
        $this->nodeIndexer->flush();
        sleep(1);
        self::assertFalse($this->nodeExistsInIndex($testNode), 'Node still exists after delete');
    }

    /**
     * @test
     */
    public function nodeMoveIsHandledCorrectly(): void
    {
        $testNode = $this->setupCrAndIndexTestNode();
        self::assertTrue($this->nodeExistsInIndex($testNode), 'Node was not successfully indexed.');

        $testNode2 = $this->siteNode->getNode('test-node-2');

        // move this node (test-node-1) into test-node-2
        $testNode->setProperty('title', 'changed');
        $testNode->moveInto($testNode2);

        // re-index
        $this->nodeIndexer->indexNode($testNode);
        $this->nodeIndexer->flush();
        sleep(1);

        // check if we do have more than one single occurrence (nodeExistsInIndex will check that indirectly)
        self::assertTrue($this->nodeExistsInIndex($testNode), 'Node was not successfully indexed.');

        // check the node path in es after indexing
        $pathInEs = $this->getNeosPathOfNodeInIndex($testNode);
        self::assertNotNull($pathInEs, 'Node does not exist after indexing');
        self::assertEquals($pathInEs, $testNode->getPath(), 'Wrong node path in elasticsearch after indexing');
    }

    /**
     * Fetch the node path (stored in elasticsearch) of the given node
     */
    private function getNeosPathOfNodeInIndex(NodeInterface $node): ?string
    {
        $this->searchClient->setContextNode($this->siteNode);
        /** @var FilteredQuery $query */
        $query = $this->objectManager->get(QueryInterface::class);
        $query->queryFilter('term', ['neos_node_identifier' => $node->getIdentifier()]);

        $result = $this->nodeIndexer->getIndex()->request('GET', '/_search', [], $query->toArray())->getTreatedContent();

        $firstHit = current(Arrays::getValueByPath($result, 'hits.hits'));

        if ($firstHit === false) {
            return null;
        }

        return Arrays::getValueByPath($firstHit, '_source.neos_path');
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

    /**
     * @return NodeInterface
     * @throws ConfigurationException
     * @throws Exception
     * @throws \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception
     * @throws \Neos\Flow\Http\Exception
     */
    protected function setupCrAndIndexTestNode(): NodeInterface
    {
        $this->setupContentRepository();
        $this->createNodesForNodeSearchTest();
        /** @var NodeInterface $testNode */
        $testNode = current($this->siteNode->getChildNodes('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document', 1));

        $this->nodeIndexer->setDimensions($testNode->getDimensions());
        $this->nodeIndexer->getIndex()->create();

        $nodeTypeMappingCollection = $this->nodeTypeMappingBuilder->buildMappingInformation($this->nodeIndexer->getIndex());
        foreach ($nodeTypeMappingCollection as $mapping) {
            /** @var Mapping $mapping */
            $mapping->apply();
        }

        $this->nodeIndexer->indexNode($testNode);
        $this->nodeIndexer->flush();
        sleep(1);
        return $testNode;
    }
}
