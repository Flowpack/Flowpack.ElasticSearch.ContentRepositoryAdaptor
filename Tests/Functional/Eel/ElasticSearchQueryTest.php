<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional\Eel;

/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch.ContentRepositoryAdaptor". *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use TYPO3\TYPO3CR\Domain\Model\Workspace;
use TYPO3\TYPO3CR\Domain\Repository\WorkspaceRepository;

/**
 * Testcase for ElasticSearchQuery
 */
class ElasticSearchQueryTest extends \TYPO3\Flow\Tests\FunctionalTestCase
{
    /**
     * @var WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Command\NodeIndexCommandController
     */
    protected $nodeIndexCommandController;

    /**
     * @var \TYPO3\TYPO3CR\Domain\Service\Context
     */
    protected $context;

    /**
     * @var boolean
     */
    protected static $testablePersistenceEnabled = true;

    /**
     * @var \TYPO3\TYPO3CR\Domain\Service\ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @var \TYPO3\TYPO3CR\Domain\Service\NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @var \TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository
     */
    protected $nodeDataRepository;

    /**
     * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryBuilder
     */
    protected $queryBuilder;


    protected static $indexInitialized = false;


    public function setUp()
    {
        parent::setUp();
        $this->workspaceRepository = $this->objectManager->get('TYPO3\TYPO3CR\Domain\Repository\WorkspaceRepository');
        $liveWorkspace = new Workspace("live");
        $this->workspaceRepository->add($liveWorkspace);

        $this->nodeTypeManager = $this->objectManager->get('TYPO3\TYPO3CR\Domain\Service\NodeTypeManager');
        $this->contextFactory = $this->objectManager->get('TYPO3\TYPO3CR\Domain\Service\ContextFactoryInterface');
        $this->context = $this->contextFactory->create(array('workspaceName' => 'live', 'dimensions' => array('language' => array('de'))));

        $this->nodeDataRepository = $this->objectManager->get('TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository');
        $this->queryBuilder = $this->objectManager->get('Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryBuilder');

        $this->nodeIndexCommandController = $this->objectManager->get('Flowpack\ElasticSearch\ContentRepositoryAdaptor\Command\NodeIndexCommandController');

        $this->queryBuilder->log();
        $this->initializeIndex();
        $this->createNodesForNodeSearchTest();
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->inject($this->contextFactory, 'contextInstances', array());
        // $this->nodeIndexCommandController->cleanupCommand();
    }

    /**
     * @test
     */
    public function filterByNodeType()
    {
        $resultCount = $this->queryBuilder->query($this->context->getRootNode())->nodeType('TYPO3.Neos.NodeTypes:Page')->count();
        $this->assertEquals(3, $resultCount);
    }

    /**
     * @test
     */
    public function filterNodeByProperty()
    {
        $resultCount = $this->queryBuilder->query($this->context->getRootNode())->exactMatch('title', 'ei')->count();
        $this->assertEquals(1, $resultCount);
    }

    /**
     * @test
     */
    public function filterLimitQuery()
    {
        $resultCount = $this->queryBuilder->query($this->context->getRootNode())->limit(1)->count();
        $this->assertEquals(3, $resultCount, 'Asserting the count query returns the total count.');

        $result = $this->queryBuilder->query($this->context->getRootNode())->limit(1)->execute();

        $this->assertEquals(1, $result->getAccessibleCount(), 'Asserting that getAccessibleCount returns the correct number');
        $this->assertCount(1, $result->toArray(), 'Asserting the executed query returns a valid number of items.');
    }

    /**
     * @test
     */
    public function fieldBasedAggregations()
    {
        $aggregationTitle = "titleagg";
        $result = $this->queryBuilder->query($this->context->getRootNode())->fieldBasedAggregation($aggregationTitle, "title")->execute()->getAggregations();

        $this->assertArrayHasKey($aggregationTitle, $result);

        $this->assertCount(2, $result[$aggregationTitle]['buckets']);

        $expectedChickenBucket = array(
            'key' => 'chicken',
            'doc_count' => 2
        );

        $this->assertEquals($expectedChickenBucket, $result[$aggregationTitle]['buckets'][0]);
    }

    /**
     * Creates some sample nodes to run tests against
     */
    protected function createNodesForNodeSearchTest()
    {
        $rootNode = $this->context->getRootNode();

        $newNode1 = $rootNode->createNode('test-node-1', $this->nodeTypeManager->getNodeType('TYPO3.Neos.NodeTypes:Page'));
        $newNode1->setProperty('title', 'chicken');

        $newNode2 = $rootNode->createNode('test-node-2', $this->nodeTypeManager->getNodeType('TYPO3.Neos.NodeTypes:Page'));
        $newNode2->setProperty('title', 'chicken');

        $newNode3 = $rootNode->createNode('test-node-3', $this->nodeTypeManager->getNodeType('TYPO3.Neos.NodeTypes:Page'));
        $newNode3->setProperty('title', 'Ei');

        $dimensionContext = $this->contextFactory->create(array('workspaceName' => 'live', 'dimensions' => array('language' => array('en_us'))));
        $translatedNode3 = $dimensionContext->adoptNode($newNode3, TRUE);
        $translatedNode3->setProperty('title', 'egg');

        $this->persistenceManager->persistAll();
        $this->nodeIndexCommandController->buildCommand();
    }

    protected function initializeIndex()
    {
        if (self::$indexInitialized === false) {
            // we need to make sure that the index will be prefixed with an unique name. so we add a sleep as it is not
            // possible right now to set the index name
            sleep(2);
            $this->nodeIndexCommandController->buildCommand();

            self::$indexInitialized = true;
        }
    }
}
