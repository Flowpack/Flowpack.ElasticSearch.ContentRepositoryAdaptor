<?php

declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Unit\Eel;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQuery;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryBuilder;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryResult;
use Neos\Flow\Tests\UnitTestCase;

/**
 * Testcase for ElasticSearchQueryBuilder
 */
class ElasticSearchQueryBuilderResultTest extends UnitTestCase
{

    /**
     * @test
     */
    public function ifNoAggregationsAreSetInTheQueyBuilderResultAnEmptyArrayWillBeReturnedIfYouFetchTheAggregations(): void
    {
        $resultArrayWithoutAggregations = [
            'nodes' => ['some', 'nodes']
        ];

        $queryBuilder = $this->getMockBuilder(ElasticSearchQueryBuilder::class)->setMethods(['fetch'])->getMock();
        $queryBuilder->method('fetch')->willReturn($resultArrayWithoutAggregations);

        $esQuery = new ElasticSearchQuery($queryBuilder);

        $queryResult = new ElasticSearchQueryResult($esQuery);

        $actual = $queryResult->getAggregations();

        $this->assertIsArray($actual);
        $this->assertEmpty($actual);
    }
}
