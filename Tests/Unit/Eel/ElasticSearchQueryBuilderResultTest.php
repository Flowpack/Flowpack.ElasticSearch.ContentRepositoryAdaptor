<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Unit\Eel;

/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch.ContentRepositoryAdaptor". *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryBuilder;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryResult;

/**
 * Testcase for ElasticSearchQueryBuilder
 */
class ElasticSearchQueryBuilderResultTest extends \TYPO3\Flow\Tests\UnitTestCase
{

    /**
     * @test
     */
    public function ifNoAggregationsAreSetInTheQueyBuilderResultAnEmptyArrayWillBeReturnedIfYouFetchTheAggregations()
    {
        $resultArrayWithoutAggregations = array(
            "nodes" => array("some", "nodes")
        );

        $queryBuilder = $this->getMock(ElasticSearchQueryBuilder::class, array("fetch"));
        $queryBuilder->method("fetch")->will($this->returnValue($resultArrayWithoutAggregations));

        $esQuery = new \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQuery($queryBuilder);

        $queryResult = new ElasticSearchQueryResult($esQuery);

        $actual = $queryResult->getAggregations();

        $this->assertTrue(is_array($actual));
        $this->assertEmpty($actual);
    }
}