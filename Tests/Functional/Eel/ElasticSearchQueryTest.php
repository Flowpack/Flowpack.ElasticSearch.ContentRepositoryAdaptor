<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional\Eel;

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
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryResult;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\QueryBuildingException;
use Neos\ContentRepository\Exception\NodeExistsException;
use Neos\ContentRepository\Exception\NodeTypeNotFoundException;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Persistence\QueryResultInterface;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Search\Search\QueryBuilderInterface;

class ElasticSearchQueryTest extends FunctionalTestCase
{
    /**
     * @var WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @var NodeIndexCommandController
     */
    protected $nodeIndexCommandController;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var NodeInterface
     */
    protected $siteNode;

    /**
     * @var boolean
     */
    protected static $testablePersistenceEnabled = true;

    /**
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @var NodeDataRepository
     */
    protected $nodeDataRepository;

    /**
     * @var boolean
     */
    protected static $indexInitialized = false;

    public function setUp()
    {
        parent::setUp();
        $this->workspaceRepository = $this->objectManager->get(WorkspaceRepository::class);
        $liveWorkspace = new Workspace('live');
        $this->workspaceRepository->add($liveWorkspace);

        $this->nodeTypeManager = $this->objectManager->get(NodeTypeManager::class);
        $this->contextFactory = $this->objectManager->get(ContextFactoryInterface::class);
        $this->context = $this->contextFactory->create([
            'workspaceName' => 'live',
            'dimensions' => ['language' => ['en_US']],
            'targetDimensions' => ['language' => 'en_US']
        ]);
        $rootNode = $this->context->getRootNode();

        $this->siteNode = $rootNode->createNode('welcome', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $this->siteNode->setProperty('title', 'welcome');

        $this->nodeDataRepository = $this->objectManager->get(NodeDataRepository::class);

        $this->nodeIndexCommandController = $this->objectManager->get(NodeIndexCommandController::class);

        $this->createNodesForNodeSearchTest();
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->inject($this->contextFactory, 'contextInstances', []);
    }

    /**
     * @test
     */
    public function elasticSearchQueryBuilderStartsClean()
    {
        /** @var ElasticSearchQueryBuilder $query */
        $query = $this->objectManager->get(ElasticSearchQueryBuilder::class);
        $cleanRequestArray = $query->getRequest()->toArray();
        $query->nodeType('Neos.NodeTypes:Page');

        $query2 = $this->objectManager->get(ElasticSearchQueryBuilder::class);

        $this->assertNotSame($query->getRequest(), $query2->getRequest());
        $this->assertEquals($cleanRequestArray, $query2->getRequest()->toArray());
    }

    /**
     * @test
     */
    public function fullTextSearchReturnsTheDocumentNode()
    {
        /** @var ElasticSearchQueryResult $result */
        $result = $this->getQueryBuilder()
            ->fulltext('circum*')
            ->execute();
        $this->assertEquals(1, $result->count());

        /** @var NodeInterface $node */
        $node = $result->current();
        $this->assertEquals('Neos.NodeTypes:Page', $node->getNodeType()->getName());
        $this->assertEquals('test-node-1', $node->getName());
    }

    /**
     * @test
     */
    public function fullTextHighlighting()
    {
        /** @var ElasticSearchQueryBuilder $queryBuilder */
        $queryBuilder = $this->getQueryBuilder();

        /** @var NodeInterface $resultNode */
        $resultNode = $queryBuilder
            ->fulltext('whistles')
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->execute()
            ->current();
        $searchHitForNode = $queryBuilder->getFullElasticSearchHitForNode($resultNode);
        $highlightedText = current($searchHitForNode['highlight']['__fulltext.text']);
        $expected = 'A Scout smiles and <em>whistles</em> under all circumstances.';
        $this->assertEquals($expected, $highlightedText);
    }

    /**
     * @test
     */
    public function filterByNodeType()
    {
        $resultCount = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Neos.NodeTypes:Page')
            ->count();
        $this->assertEquals(4, $resultCount);
    }

    /**
     * @test
     */
    public function filterNodeByProperty()
    {
        $resultCount = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->exactMatch('title', 'egg')
            ->count();
        $this->assertEquals(1, $resultCount);
    }

    /**
     * @test
     */
    public function limitDoesNotImpactCount()
    {
        $query = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Neos.NodeTypes:Page')
            ->limit(1);

        $resultCount = $query->count();
        $this->assertEquals(4, $resultCount, 'Asserting the count query returns the total count.');
    }

    /**
     * @test
     */
    public function limitImpactGetAccessibleCount()
    {
        $query = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->limit(1);

        $result = $query->execute();

        $this->assertEquals(1, $result->getAccessibleCount(), 'Asserting that getAccessibleCount returns the correct number');
        $this->assertCount(1, $result->toArray(), 'Asserting the executed query returns a valid number of items.');
    }

    /**
     * @test
     */
    public function fieldBasedAggregations()
    {
        $aggregationTitle = 'titleagg';
        $result = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->fieldBasedAggregation($aggregationTitle, 'title')
            ->execute()
            ->getAggregations();

        $this->assertArrayHasKey($aggregationTitle, $result);

        $this->assertCount(3, $result[$aggregationTitle]['buckets']);

        $expectedChickenBucket = [
            'key' => 'chicken',
            'doc_count' => 2
        ];

        $this->assertEquals($expectedChickenBucket, $result[$aggregationTitle]['buckets'][0]);
    }

    /**
     * @return array
     */
    public function termSuggestionDataProvider()
    {
        return [
            'singleWord' => [
                'term' => 'chickn',
                'expectedBestSuggestions' => [
                    'chicken'
                ]
            ],
            'multiWord' => [
                'term' => 'chickn eggs',
                'expectedBestSuggestions' => [
                    'chicken',
                    'egg'
                ]
            ],
        ];
    }

    /**
     * @dataProvider termSuggestionDataProvider
     * @test
     *
     * @param string $term
     * @param array $expectedBestSuggestions
     */
    public function termSuggestion($term, $expectedBestSuggestions)
    {
        $result = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->termSuggestions($term, 'title_analyzed')
            ->execute()
            ->getSuggestions();

        $this->assertArrayHasKey('suggestions', $result, 'The result should contain a key suggestions but looks like this ' . print_r($result, 1));
        $this->assertTrue(is_array($result['suggestions']), 'Suggestions must be an array.');
        $this->assertCount(count($expectedBestSuggestions), $result['suggestions'], sprintf('Expected %s suggestions "[%s]" but got %s suggestions', count($expectedBestSuggestions), implode(',', $expectedBestSuggestions), print_r($result['suggestions'], 1)));

        foreach ($expectedBestSuggestions as $key => $expectedBestSuggestion) {
            $suggestion = $result['suggestions'][$key];
            $this->assertArrayHasKey('options', $suggestion);
            $this->assertCount(1, $suggestion['options']);
            $this->assertEquals($expectedBestSuggestion, $suggestion['options'][0]['text']);
        }
    }

    /**
     * @test
     */
    public function nodesWillBeSortedDesc()
    {
        $result = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Neos.NodeTypes:Page')
            ->sortDesc('title')
            ->execute();

        /** @var QueryResultInterface $result $node */

        $this->assertInstanceOf(QueryResultInterface::class, $result);
        $this->assertCount(4, $result, 'The result should have 3 items');
        $this->assertEquals(4, $result->count(), 'Count should be 3');

        $node = $result->getFirst();

        $this->assertInstanceOf(NodeInterface::class, $node);
        $this->assertEquals('welcome', $node->getProperty('title'), 'Asserting a desc sort order by property title');
    }

    /**
     * @test
     */
    public function nodesWillBeSortedAsc()
    {
        $result = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Neos.NodeTypes:Page')
            ->sortAsc('title')
            ->execute();
        /** @var ElasticSearchQueryResult $result */
        $node = $result->getFirst();

        $this->assertInstanceOf(NodeInterface::class, $node);
        $this->assertEquals('chicken', $node->getProperty('title'), 'Asserting a asc sort order by property title');
    }

    /**
     * @test
     */
    public function sortValuesAreReturned()
    {
        $result = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Neos.NodeTypes:Page')
            ->sortAsc('title')
            ->execute();

        foreach ($result as $node) {
            $this->assertEquals([$node->getProperty('title')], $result->getSortValuesForNode($node));
        }
    }

    /**
     * @test
     * @throws QueryBuildingException
     * @throws \Flowpack\ElasticSearch\Exception
     */
    public function cacheLifetimeIsCalculatedCorrectly()
    {
        $cacheLifetime = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Neos.NodeTypes:Text')
            ->sortAsc('title')
            ->cacheLifetime();

        $this->assertEquals(600, $cacheLifetime);
    }

    /**
     * @return string
     */
    protected function getLogMessagePrefix($method)
    {
        return substr(strrchr($method, '\\'), 1);
    }

    /**
     * Creates some sample nodes to run tests against
     * @throws NodeExistsException
     * @throws NodeTypeNotFoundException
     * @throws StopActionException
     */
    protected function createNodesForNodeSearchTest()
    {
        $newDocumentNode1 = $this->siteNode->createNode('test-node-1', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $newDocumentNode1->setProperty('title', 'chicken');
        $newDocumentNode1->setProperty('title_analyzed', 'chicken');

        $newContentNode1 = $newDocumentNode1->getNode('main')->createNode('document-1-text-1', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Text'));
        $newContentNode1->setProperty('text', 'A Scout smiles and whistles under all circumstances.');

        $newDocumentNode2 = $this->siteNode->createNode('test-node-2', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $newDocumentNode2->setProperty('title', 'chicken');
        $newDocumentNode2->setProperty('title_analyzed', 'chicken');

        // Nodes for cacheLifetime test
        $newContentNode2 = $newDocumentNode2->getNode('main')->createNode('document-2-text-1', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Text'));
        $newContentNode2->setProperty('text', 'Hidden after 2025-01-01');
        $newContentNode2->setHiddenAfterDateTime(new \DateTime('@1735686000'));
        $newContentNode3 = $newDocumentNode2->getNode('main')->createNode('document-2-text-2', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Text'));
        $newContentNode3->setProperty('text', 'Hidden before 2018-07-18');
        $newContentNode3->setHiddenBeforeDateTime(new \DateTime('@1531864800'));

        $newDocumentNode3 = $this->siteNode->createNode('test-node-3', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $newDocumentNode3->setProperty('title', 'egg');
        $newDocumentNode3->setProperty('title_analyzed', 'egg');


        $dimensionContext = $this->contextFactory->create([
            'workspaceName' => 'live',
            'dimensions' => ['language' => ['de']]
        ]);
        $translatedNode3 = $dimensionContext->adoptNode($newDocumentNode3, true);
        $translatedNode3->setProperty('title', 'De');

        $this->persistenceManager->persistAll();

        sleep(2);

        if (self::$indexInitialized === true) {
            return;
        }

        $this->nodeIndexCommandController->buildCommand(null, false, null, 'functionaltest');
        self::$indexInitialized = true;
    }

    /**
     * @return ElasticSearchQueryBuilder
     * @throws QueryBuildingException
     * @throws \Exception
     */
    protected function getQueryBuilder(): ElasticSearchQueryBuilder
    {
        $elasticSearchQueryBuilder = $this->objectManager->get(ElasticSearchQueryBuilder::class);
        $this->inject($elasticSearchQueryBuilder, 'now', new \DateTimeImmutable('@1735685400')); // Dec. 31, 2024 23:50:00

        return $elasticSearchQueryBuilder->query($this->siteNode);
    }
}
