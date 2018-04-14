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
     * @return QueryBuilderInterface
     */
    protected function getQueryBuilder()
    {
        /** @var ElasticSearchQueryBuilder $query */
        $query = $this->objectManager->get(ElasticSearchQueryBuilder::class);

        return $query->query($this->siteNode);
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
     * @return string
     */
    protected function getLogMessagePrefix($method)
    {
        return substr(strrchr($method, '\\'), 1);
    }

    /**
     * Creates some sample nodes to run tests against
     */
    protected function createNodesForNodeSearchTest()
    {
        $newNode1 = $this->siteNode->createNode('test-node-1', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $newNode1->setProperty('title', 'chicken');
        $newNode1->setProperty('title_analyzed', 'chicken');

        $newNode2 = $this->siteNode->createNode('test-node-2', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $newNode2->setProperty('title', 'chicken');
        $newNode2->setProperty('title_analyzed', 'chicken');

        $newNode3 = $this->siteNode->createNode('test-node-3', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $newNode3->setProperty('title', 'egg');
        $newNode3->setProperty('title_analyzed', 'egg');

        $dimensionContext = $this->contextFactory->create([
            'workspaceName' => 'live',
            'dimensions' => ['language' => ['de']]
        ]);
        $translatedNode3 = $dimensionContext->adoptNode($newNode3, true);
        $translatedNode3->setProperty('title', 'De');

        $this->persistenceManager->persistAll();

        sleep(2);

        if (self::$indexInitialized === true) {
            return;
        }

        $this->nodeIndexCommandController->buildCommand(null, false, null, 'functionaltest');
        self::$indexInitialized = true;
    }
}
