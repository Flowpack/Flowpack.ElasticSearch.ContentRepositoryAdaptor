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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryBuilder;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryResult;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\QueryBuildingException;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional\BaseElasticsearchContentRepositoryAdapterTest;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional\Traits\ContentRepositoryNodeCreationTrait;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional\Traits\ContentRepositorySetupTrait;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Persistence\QueryResultInterface;

class ElasticSearchQueryTest extends BaseElasticsearchContentRepositoryAdapterTest
{
    use ContentRepositorySetupTrait, ContentRepositoryNodeCreationTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setupContentRepository();

        $this->createNodesForNodeSearchTest();
        sleep(1);
        $this->indexNodes();
    }

    /**
     * @test
     */
    public function elasticSearchQueryBuilderStartsClean(): void
    {
        /** @var ElasticSearchQueryBuilder $query */
        $query = $this->objectManager->get(ElasticSearchQueryBuilder::class);
        $cleanRequestArray = $query->getRequest()->toArray();
        $query->nodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document');

        $query2 = $this->objectManager->get(ElasticSearchQueryBuilder::class);

        static::assertNotSame($query->getRequest(), $query2->getRequest());
        static::assertEquals($cleanRequestArray, $query2->getRequest()->toArray());
    }

    /**
     * @test
     */
    public function fullTextSearchReturnsTheDocumentNode(): void
    {
        /** @var ElasticSearchQueryResult $result */
        $result = $this->getQueryBuilder()
            ->fulltext('circum*')
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->execute();
        static::assertEquals(1, $result->count());

        /** @var NodeInterface $node */
        $node = $result->current();
        static::assertEquals('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document', $node->getNodeType()->getName());
        static::assertEquals('test-node-1', $node->getName());
    }

    /**
     * @test
     */
    public function fullTextHighlighting(): void
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
        $highlightedText = current($searchHitForNode['highlight']['neos_fulltext.text']);
        $expected = 'A Scout smiles and <em>whistles</em> under all circumstances.';
        static::assertEquals($expected, $highlightedText);
    }

    /**
     * @test
     */
    public function filterByNodeType(): void
    {
        $resultCount = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document')
            ->count();
        static::assertEquals(4, $resultCount);
    }

    /**
     * @test
     */
    public function filterNodeByProperty(): void
    {
        $resultCount = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->exactMatch('title', 'egg')
            ->count();
        static::assertEquals(1, $resultCount);
    }

    /**
     * @test
     *
     * @throws QueryBuildingException
     * @throws \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws \Neos\Flow\Http\Exception
     */
    public function prefixFilter(): void
    {
        $resultCount = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->prefix('title', 'chi')
            ->count();
        static::assertEquals(2, $resultCount);
    }

    /**
     * @test
     */
    public function limitDoesNotImpactCount(): void
    {
        $query = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document')
            ->limit(1);

        $resultCount = $query->count();
        static::assertEquals(4, $resultCount, 'Asserting the count query returns the total count.');
    }

    /**
     * @test
     */
    public function limitImpactGetAccessibleCount(): void
    {
        $query = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->limit(1);

        $result = $query->execute();

        static::assertEquals(1, $result->getAccessibleCount(), 'Asserting that getAccessibleCount returns the correct number');
        static::assertCount(1, $result->toArray(), 'Asserting the executed query returns a valid number of items.');
    }

    /**
     * @test
     */
    public function fieldBasedAggregations(): void
    {
        $aggregationTitle = 'titleagg';
        $result = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->fieldBasedAggregation($aggregationTitle, 'title')
            ->execute()
            ->getAggregations();

        static::assertArrayHasKey($aggregationTitle, $result);

        static::assertCount(3, $result[$aggregationTitle]['buckets']);

        $expectedChickenBucket = [
            'key' => 'chicken',
            'doc_count' => 2
        ];

        $this->assertEquals($expectedChickenBucket, $result[$aggregationTitle]['buckets'][0]);
    }

    /**
     * @return array
     */
    public function termSuggestionDataProvider(): array
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
    public function termSuggestion(string $term, array $expectedBestSuggestions): void
    {
        $result = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->termSuggestions($term, 'title_analyzed')
            ->execute()
            ->getSuggestions();

        static::assertArrayHasKey('suggestions', $result, 'The result should contain a key suggestions but looks like this ' . print_r($result, true));
        static::assertIsArray($result['suggestions']);
        static::assertCount(count($expectedBestSuggestions), $result['suggestions'], sprintf('Expected %s suggestions "[%s]" but got %s suggestions', count($expectedBestSuggestions), implode(',', $expectedBestSuggestions), print_r($result['suggestions'], true)));

        foreach ($expectedBestSuggestions as $key => $expectedBestSuggestion) {
            $suggestion = $result['suggestions'][$key];
            static::assertArrayHasKey('options', $suggestion);
            static::assertCount(1, $suggestion['options']);
            static::assertEquals($expectedBestSuggestion, $suggestion['options'][0]['text']);
        }
    }

    /**
     * @test
     */
    public function nodesWillBeSortedDesc(): void
    {
        $result = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document')
            ->sortDesc('title')
            ->execute();

        /** @var QueryResultInterface $result $node */

        static::assertInstanceOf(QueryResultInterface::class, $result);
        static::assertCount(4, $result, 'The result should have 3 items');
        static::assertEquals(4, $result->count(), 'Count should be 3');

        $node = $result->getFirst();

        static::assertInstanceOf(NodeInterface::class, $node);
        static::assertEquals('welcome', $node->getProperty('title'), 'Asserting a desc sort order by property title');
    }

    /**
     * @test
     */
    public function nodesWillBeSortedAsc(): void
    {
        $result = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document')
            ->sortAsc('title')
            ->execute();
        /** @var ElasticSearchQueryResult $result */
        $node = $result->getFirst();

        static::assertInstanceOf(NodeInterface::class, $node);
        static::assertEquals('chicken', $node->getProperty('title'), 'Asserting a asc sort order by property title');
    }

    /**
     * @test
     */
    public function sortValuesAreReturned(): void
    {
        $result = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document')
            ->sortAsc('title')
            ->execute();

        foreach ($result as $node) {
            static::assertEquals([$node->getProperty('title')], $result->getSortValuesForNode($node));
        }
    }

    /**
     * @test
     * @throws QueryBuildingException
     * @throws \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws \Neos\Flow\Http\Exception
     */
    public function cacheLifetimeIsCalculatedCorrectly(): void
    {
        $cacheLifetime = $this->getQueryBuilder()
            ->log($this->getLogMessagePrefix(__METHOD__))
            ->nodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Content')
            ->sortAsc('title')
            ->cacheLifetime();

        static::assertEquals(600, $cacheLifetime);
    }
}
