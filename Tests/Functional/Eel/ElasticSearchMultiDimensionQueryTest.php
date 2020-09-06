<?php
declare(strict_types=1);

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

use DateTime;
use DateTimeImmutable;
use Exception;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Command\NodeIndexCommandController;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryBuilder;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryResult;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional\Traits\ContentRepositoryMultiDimensionNodeCreationTrait;
use Neos\ContentRepository\Domain\Model\NodeInterface;

use Neos\Flow\Tests\FunctionalTestCase;

class ElasticSearchMultiDimensionQueryTest extends FunctionalTestCase
{
    use ContentRepositoryMultiDimensionNodeCreationTrait;

    const TESTING_INDEX_PREFIX = 'neoscr_testing';

    /**
     * @var ElasticSearchClient
     */
    protected $searchClient;

    /**
     * @var NodeIndexCommandController
     */
    protected $nodeIndexCommandController;

    /**
     * @var boolean
     */
    protected static $testablePersistenceEnabled = true;

    /**
     * @var NodeIndexer
     */
    protected $nodeIndexer;

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
    protected $siteNodeDk;

    /**
     * @var boolean
     */
    protected static $indexInitialized = false;


    public function setUp(): void
    {
        parent::setUp();

        $this->nodeIndexer = $this->objectManager->get(NodeIndexer::class);
        $this->searchClient = $this->objectManager->get(ElasticSearchClient::class);

        if (self::$indexInitialized !== true) {
            // clean up any existing indices
            $this->searchClient->request('DELETE', '/' . self::TESTING_INDEX_PREFIX . '*');
        }


        $this->nodeIndexCommandController = $this->objectManager->get(NodeIndexCommandController::class);
        $this->setupContentRepository();
        $this->createNodesForNodeSearchTest();
        $this->indexNodes();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->inject($this->contextFactory, 'contextInstances', []);
    }

    /**
     * @test
     */
    public function countDefaultDimensionNodesTest(): void
    {
        $resultDefault = $this->getQueryBuilder()
            ->query($this->siteNodeDefault)
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document')
            ->sortDesc('title')
            ->execute();

        static::assertCount(3, $resultDefault->toArray());
        static::assertNodeNames(['root', 'document1', 'document-untranslated'], $resultDefault);
    }

    /**
     * @test
     */
    public function countDeDimensionNodesTest(): void
    {
        $resultDe = $this->getQueryBuilder()
            ->query($this->siteNodeDe)
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document')
            ->sortDesc('title')
            ->execute();

        // expecting: root, document1, document2, document3, document4, untranslated (fallback from en_us) = 6
        static::assertCount(6, $resultDe->toArray(), 'Found nodes: ' . implode(',', self::extractNodeNames($resultDe)));
        static::assertNodeNames(['root', 'document1', 'document2-de', 'document3-de', 'document4-de', 'document-untranslated'], $resultDe);
    }

    /**
     * @test
     */
    public function countDkDimensionNodesTest(): void
    {
        $resultDk = $this->getQueryBuilder()
            ->query($this->siteNodeDk)
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document')
            ->sortDesc('title')
            ->execute();

        // expecting: root, document1, document2, document4 (fallback from de), untranslated (fallback from en_us) = 6
        static::assertCount(5, $resultDk->toArray());
        static::assertNodeNames(['root', 'document1', 'document2-dk', 'document4-de', 'document-untranslated'], $resultDk);
    }


    protected function indexNodes(): void
    {
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

    private static function extractNodeNames(ElasticSearchQueryResult $result): array
    {
        return array_map(static function (NodeInterface $node) {
            return $node->getName();
        }, $result->toArray());
    }

    private static function assertNodeNames(array $expectedNames, ElasticSearchQueryResult $actualResult): void
    {
        sort($expectedNames);

        $actualNames = self::extractNodeNames($actualResult);
        sort($actualNames);

        self::assertEquals($expectedNames, $actualNames);
    }
}
