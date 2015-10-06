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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryResult;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ViewHelpers\GetHitArrayForNodeViewHelper;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

/**
 * Testcase for ElasticSearchQueryBuilder
 */
class GetHitArrayForNodeViewHelperTest extends \TYPO3\Flow\Tests\UnitTestCase
{
    /**
     * @var GetHitArrayForNodeViewHelper
     */
    protected $viewHelper;

    public function setUp()
    {
        $this->viewHelper = new GetHitArrayForNodeViewHelper();
    }

    /**
     * @test
     */
    public function ifNoPathIsSetTheFullHitArrayWillBeReturned()
    {
        $hitArray = [
            'sort' => [
                0 => '14'
            ]
        ];

        $node = $this->getMock(NodeInterface::class);

        $queryResult = $this->getMock(ElasticSearchQueryResult::class, array('searchHitForNode'), array(), '', false);
        $queryResult->expects($this->once())->method('searchHitForNode')->willReturn($hitArray);

        $result = $this->viewHelper->render($queryResult, $node);
        $this->assertEquals($hitArray, $result, 'The full hit array will be returned');
    }

    /**
     * @test
     */
    public function viewHelperWillReturnAPathFromHitArray()
    {
        $path = 'sort';
        $hitArray = [
            'foo'   => 'bar',
            $path  => [
                0 => '14',
                1 => '18'
            ]
        ];

        $node = $this->getMock(NodeInterface::class);

        $queryResult = $this->getMock(ElasticSearchQueryResult::class, array('searchHitForNode'), array(), '', false);
        $queryResult->expects($this->once())->method('searchHitForNode')->willReturn($hitArray);

        $result = $this->viewHelper->render($queryResult, $node, $path);
        $this->assertEquals($hitArray[$path], $result, 'Just a path from the full hit array will be returned');
    }

    /**
     * @test
     */
    public function aSingleValueWillBeReturnedForADottedPath()
    {
        $singleValue = 'bar';
        $hitArray = [
            'foo'   => [
                0 => $singleValue
            ],
            'sort'  => [
                0 => '14',
                0 => '14',
                1 => '18'
            ]
        ];

        $node = $this->getMock(NodeInterface::class);

        $queryResult = $this->getMock(ElasticSearchQueryResult::class, array('searchHitForNode'), array(), '', false);
        $queryResult->expects($this->exactly(2))->method('searchHitForNode')->willReturn($hitArray);

        $singleResult = $this->viewHelper->render($queryResult, $node, 'foo.0');
        $this->assertEquals($singleValue, $singleResult, 'Only a single value will be returned if path is dotted');

        $fullResult = $this->viewHelper->render($queryResult, $node, 'sort');
        $this->assertEquals($hitArray['sort'], $fullResult, 'Full array will be returned if there are multiple values');
    }
}