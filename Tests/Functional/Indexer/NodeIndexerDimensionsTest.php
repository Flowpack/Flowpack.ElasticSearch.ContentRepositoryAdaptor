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
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
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
    const TESTING_INDEX_PREFIX = 'neoscr_testing';

    /**
     * @var ElasticSearchClient
     */
    protected $searchClient;
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
    protected $siteNodeDefault;

    /**
     * @var NodeInterface
     */
    protected $siteNodeDe;

    /**
     * @var NodeInterface
     */
    protected $siteNodeFr;

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
        $this->searchClient = $this->objectManager->get(ElasticSearchClient::class);
        // clean up any existing indices
        $this->searchClient->request('DELETE', '/' . self::TESTING_INDEX_PREFIX . '*');

        $this->nodeIndexCommandController = $this->objectManager->get(NodeIndexCommandController::class);
        $this->nodeTypeManager = $this->objectManager->get(NodeTypeManager::class);
        $this->contextFactory = $this->objectManager->get(ContextFactoryInterface::class);
        $this->createNodesForLanguageDimensions();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->inject($this->contextFactory, 'contextInstances', []);
        //$this->searchClient->request('DELETE', '/' . self::TESTING_INDEX_PREFIX . '*');
    }

    /**
     * @test
     */
    public function countNodesTest(): void
    {
        $queryBuilder = $this->getQueryBuilder();
        $resultDefault = $queryBuilder
            ->query($this->siteNodeDefault)
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Neos.NodeTypes:Page')
            ->sortDesc('title')
            ->execute();

        /** @var QueryResultInterface $resultDefault */
        static::assertCount(1, $resultDefault, 'The result should have 2 items');
        static::assertEquals(1, $resultDefault->count(), 'Count should be 2');

        $resultDe = $queryBuilder
            ->query($this->siteNodeDe)
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Neos.NodeTypes:Page')
            ->sortDesc('title')
            ->execute();

        /** @var QueryResultInterface $resultDe */

        static::assertCount(4, $resultDe, 'The result should have 4 items');
        static::assertEquals(4, $resultDe->count(), 'Count should be 4');

        $resultFr = $queryBuilder
            ->query($this->siteNodeFr)
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Neos.NodeTypes:Page')
            ->sortDesc('title')
            ->execute();

        /** @var QueryResultInterface $resultFr */

        static::assertCount(3, $resultFr, 'The result should have 3 items');
        static::assertEquals(3, $resultFr->count(), 'Count should be 3');
    }


    /**
     * add test data that moreless look like this:
     * -root-[en_US|de|fr]
     *   |- site-[en_US|de|fr]
     *     |- document2-[de|fr]
     *       |- document3-de
     *       |- document4-[de|fr]
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
        $defaultLanguageDimensionContext = $this->contextFactory->create([
            'workspaceName' => 'live',
            'dimensions' => ['language' => ['en_US']],
            'targetDimensions' => ['language' => 'en_US']
        ]);
        $deLanguageDimensionContext = $this->contextFactory->create([
            'workspaceName' => 'live',
            'dimensions' => ['language' => ['de']],
            'targetDimensions' => ['language' => 'de']
        ]);
        $frLanguageDimensionContext = $this->contextFactory->create([
            'workspaceName' => 'live',
            'dimensions' => ['language' => ['fr']],
            'targetDimensions' => ['language' => 'fr']
        ]);

        $rootNode = $defaultLanguageDimensionContext->getRootNode();
        $this->siteNodeDefault = $rootNode->createNode('root', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $this->siteNodeDefault->setProperty('title', 'root-default');
        $this->siteNodeDe = $deLanguageDimensionContext->adoptNode($this->siteNodeDefault, true);
        $this->siteNodeDe->setProperty('title', 'root-de');
        $this->siteNodeFr = $deLanguageDimensionContext->adoptNode($this->siteNodeDefault, true);
        $this->siteNodeFr->setProperty('title', 'root-fr');

        // add a document node that is translated in two languages
        $newDocumentNode1 = $this->siteNodeDefault->createNode('site-default', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $newDocumentNode1->setProperty('title', 'site-default');

        $translatedDocumentNode1De = $deLanguageDimensionContext->adoptNode($newDocumentNode1, true);
        $translatedDocumentNode1De->setProperty('title', 'site-de');
        $translatedDocumentNode1Fr = $frLanguageDimensionContext->adoptNode($newDocumentNode1, true);
        $translatedDocumentNode1Fr->setProperty('title', 'site-fr');


        // add additional, but separate nodes here
        $standaloneDocumentNode2De = $this->siteNodeDe->createNode('document2-de', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $standaloneDocumentNode2De->setProperty('title', 'document2-de');

        $standaloneDocumentNode2Fr = $this->siteNodeFr->createNode('document2-fr', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $standaloneDocumentNode2Fr->setProperty('title', 'document2-fr');

        // add an additional german node
        $documentNodeDe3 = $standaloneDocumentNode2De->createNode('document3-de', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $documentNodeDe3->setProperty('title', 'document3-de');

        // add another german node, but translate it to english
        $documentNodeDe4 = $standaloneDocumentNode2De->createNode('document4-de', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $documentNodeDe4->setProperty('title', 'document4-de');

        $translatedDocumentNode4Fr = $frLanguageDimensionContext->adoptNode($documentNodeDe4, true);
        $translatedDocumentNode4Fr->setProperty('title', 'document4-fr');

        $this->persistenceManager->persistAll();

        sleep(2);

        if (self::$indexInitialized === true) {
            return;
        }

        $this->nodeIndexCommandController->buildCommand(null, false, 'live');
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
