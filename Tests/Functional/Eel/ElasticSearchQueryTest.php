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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQuery;

/**
 * Testcase for ElasticSearchQuery
 */
class ElasticSearchQueryTest extends \TYPO3\Flow\Tests\FunctionalTestCase
{

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

    public function setUp()
    {
        parent::setUp();
        $this->nodeTypeManager = $this->objectManager->get('TYPO3\TYPO3CR\Domain\Service\NodeTypeManager');
        $this->contextFactory = $this->objectManager->get('TYPO3\TYPO3CR\Domain\Service\ContextFactoryInterface');
        $this->context = $this->contextFactory->create(array('workspaceName' => 'live', 'dimensions' => array('language' => array('de'))));
        $this->nodeDataRepository = $this->objectManager->get('TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository');
        $this->queryBuilder = $this->objectManager->get('Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryBuilder');

        $this->queryBuilder->log();
        $this->createNodesForNodeSearchTest();

        $this->nodeIndexCommandController = $this->objectManager->get('Flowpack\ElasticSearch\ContentRepositoryAdaptor\Command\NodeIndexCommandController');
        $this->nodeIndexCommandController->buildCommand();

    }


    public function tearDown()
    {
        parent::tearDown();
        $this->inject($this->contextFactory, 'contextInstances', array());
        $this->nodeIndexCommandController->cleanupCommand();
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
        $resultCount = $this->queryBuilder->query($this->context->getRootNode())->exactMatch('title', 'egg')->count();
        $this->assertEquals(1, $resultCount);
    }

    /**
     * @test
     */
    public function filterLimitQuery()
    {
        $resultCount = $this->queryBuilder->query($this->context->getRootNode())->limit(1)->count();
        $this->assertEquals(6, $resultCount, 'Asserting the count query returns the total count.');

        $result = $this->queryBuilder->query($this->context->getRootNode())->limit(1)->execute();
        $this->assertCount(1, $result, 'Asserting the executed query returns a valid number of items.');
        $this->assertEquals(1, $result->getAccessibleCount(), 'Asserting that getAccessibleCount returns the correct number');
    }


    protected function createNodesForNodeSearchTest() {
        $rootNode = $this->context->getRootNode();

        $newNode1 = $rootNode->createNode('test-node-1', $this->nodeTypeManager->getNodeType('TYPO3.Neos.NodeTypes:Page'));
        $newNode1->setProperty('title', 'chicken');

        $newNode2 = $rootNode->createNode('test-node-2', $this->nodeTypeManager->getNodeType('TYPO3.Neos.NodeTypes:Page'));
        $newNode2->setProperty('title', 'chicken');

        $newNode2 = $rootNode->createNode('test-node-3', $this->nodeTypeManager->getNodeType('TYPO3.Neos.NodeTypes:Page'));
        $newNode2->setProperty('title', 'egg');

        $this->persistenceManager->persistAll();
    }
}