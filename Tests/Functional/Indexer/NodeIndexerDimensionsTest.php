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

use DateTime;
use DateTimeImmutable;
use Exception;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Command\NodeIndexCommandController;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryBuilder;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryResult;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\QueryBuildingException;
use Flowpack\ElasticSearch\Transfer\Exception\ApiException;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Exception\NodeExistsException;
use Neos\ContentRepository\Exception\NodeTypeNotFoundException;
use Neos\Flow\Cli\Exception\StopCommandException;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Persistence\QueryResultInterface;
use Neos\Flow\Tests\FunctionalTestCase;

class NodeIndexerDimensionsTest extends FunctionalTestCase
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
     * @var NodeInterface
     */
    protected $siteNodeZz;

    /**
     * @var NodeInterface
     */
    protected $siteNodeDe;

    /**
     * @var NodeInterface
     */
    protected $siteNodeEn;

    /**
     * @var boolean
     */
    protected static $indexInitialized = false;

    public function setUp(): void
    {
        parent::setUp();
        $this->workspaceRepository = $this->objectManager->get(WorkspaceRepository::class);
        $liveWorkspace = new Workspace('live');
        $this->workspaceRepository->add($liveWorkspace);

        $this->nodeDataRepository = $this->objectManager->get(NodeDataRepository::class);
        $this->nodeIndexCommandController = $this->objectManager->get(NodeIndexCommandController::class);
        $this->nodeTypeManager = $this->objectManager->get(NodeTypeManager::class);
        $this->contextFactory = $this->objectManager->get(ContextFactoryInterface::class);
        $this->createNodesForDimensionsTest();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->inject($this->contextFactory, 'contextInstances', []);
    }

    /**
     * @test
     */
    public function countNodesTest(): void
    {
        $queryBuilder = $this->getQueryBuilder();
        $resultZz = $queryBuilder
            ->query($this->siteNodeZz)
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Neos.NodeTypes:Page')
            ->sortDesc('title')
            ->execute();

        /** @var QueryResultInterface $resultZz */

        static::assertCount(1, $resultZz, 'The result should have 1 item');
        static::assertEquals(1, $resultZz->count(), 'Count should be 1');

        $resultDe = $queryBuilder
            ->query($this->siteNodeDe)
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Neos.NodeTypes:Page')
            ->sortDesc('title')
            ->execute();

        /** @var QueryResultInterface $resultDe */

        static::assertCount(4, $resultDe, 'The result should have 4 items');
        static::assertEquals(4, $resultDe->count(), 'Count should be 4');

        $resultEn = $queryBuilder
            ->query($this->siteNodeEn)
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Neos.NodeTypes:Page')
            ->sortDesc('title')
            ->execute();

        /** @var QueryResultInterface $resultEn */

        static::assertCount(3, $resultEn, 'The result should have 3 items');
        static::assertEquals(3, $resultEn->count(), 'Count should be 3');
    }


    /**
     * add test data that moreless look like this:
     * -root-[zz|de|en]
     *   |- site-[zz|de|en]
     *     |- document2-[de|en]
     *       |- document3-de
     *       |- document4-[de|en]
     *
     * @throws NodeExistsException
     * @throws NodeTypeNotFoundException
     * @throws StopActionException
     * @throws \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception
     * @throws ApiException
     * @throws StopCommandException
     */
    protected function createNodesForLanguageDimensions(): void
    {
        $zzLanguageDimensionContext = $this->contextFactory->create([
            'workspaceName' => 'live',
            'dimensions' => ['language' => ['zz']],
            'targetDimensions' => ['language' => 'zz']
        ]);
        $deLanguageDimensionContext = $this->contextFactory->create([
            'workspaceName' => 'live',
            'dimensions' => ['language' => ['de']],
            'targetDimensions' => ['language' => 'de']
        ]);
        $enLanguageDimensionContext = $this->contextFactory->create([
            'workspaceName' => 'live',
            'dimensions' => ['language' => ['en']],
            'targetDimensions' => ['language' => 'en']
        ]);

        $rootNode = $zzLanguageDimensionContext->getRootNode();
        $this->siteNodeZz = $rootNode->createNode('root', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $this->siteNodeZz->setProperty('title', 'root-zz');
        $this->siteNodeDe = $deLanguageDimensionContext->adoptNode($this->siteNodeZz, true);
        $this->siteNodeDe->setProperty('title', 'root-de');
        $this->siteNodeEn = $deLanguageDimensionContext->adoptNode($this->siteNodeZz, true);
        $this->siteNodeEn->setProperty('title', 'root-en');

        // add a document node that is translated in two languages
        $newDocumentNode1 = $this->siteNodeZz->createNode('test-node-1', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $newDocumentNode1->setProperty('title', 'site');
        $translatedDocumentNode1De = $deLanguageDimensionContext->adoptNode($newDocumentNode1, true);
        $translatedDocumentNode1De->setProperty('title', 'site-de');
        $translatedDocumentNode1En = $enLanguageDimensionContext->adoptNode($newDocumentNode1, true);
        $translatedDocumentNode1En->setProperty('title', 'site-en');


        // add additional, but separate nodes here
        $standaloneDocumentNode2De = $this->siteNodeDe->createNode('document2-de', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $standaloneDocumentNode2De->setProperty('title', 'document2-de');
        $standaloneDocumentNode2En = $this->siteNodeEn->createNode('document2-en', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $standaloneDocumentNode2En->setProperty('title', 'document2-en');

        // add an additional german node
        $documentNodeDe3 = $standaloneDocumentNode2De->createNode('document3-de', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $documentNodeDe3->setProperty('title', 'document3-de');

        // add another german node, but translate it to english
        $documentNodeDe4 = $standaloneDocumentNode2De->createNode('document4-de', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $documentNodeDe4->setProperty('title', 'document4-de');
        $translatedDocumentNode1En = $enLanguageDimensionContext->adoptNode($documentNodeDe4, true);
        $translatedDocumentNode1En->setProperty('title', 'document4-en');

        $this->persistenceManager->persistAll();

        sleep(2);

        if (self::$indexInitialized === true) {
            return;
        }

        $this->nodeIndexCommandController->buildCommand(null, false, null, 'functionaltest');
        self::$indexInitialized = true;
    }

    /**
     * @param string $method
     * @return string
     */
    protected function getLogMessagePrefix(string $method): string
    {
        return substr(strrchr($method, '\\'), 1);
    }

    /**
     * @return ElasticSearchQueryBuilder
     */
    protected function getQueryBuilder(): ElasticSearchQueryBuilder
    {
        try {
            $elasticSearchQueryBuilder = $this->objectManager->get(ElasticSearchQueryBuilder::class);
            $this->inject($elasticSearchQueryBuilder, 'now', new DateTimeImmutable('@1735685400')); // Dec. 31, 2024 23:50:00

            return $elasticSearchQueryBuilder;
        } catch (Exception $exception) {
            static::fail('Setting up the QueryBuilder failed: ' . $exception->getMessage());
        }
    }
}