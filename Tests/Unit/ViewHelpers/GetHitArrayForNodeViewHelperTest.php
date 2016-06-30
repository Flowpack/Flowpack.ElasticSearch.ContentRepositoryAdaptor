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

    /**
     * @var NodeInterface|\PHPUnit_Framework_MockObject_MockObject $node
     */
    protected $mockNode;

    /**
     * @var ElasticSearchQueryResult|\PHPUnit_Framework_MockObject_MockObject $queryResult
     */
    protected $mockQueryResult;

    public function setUp()
    {
        $this->viewHelper = new GetHitArrayForNodeViewHelper();
        $this->mockNode = $this->createMock(NodeInterface::class);
        $this->mockQueryResult = $this->getMockBuilder(ElasticSearchQueryResult::class)->setMethods(['searchHitForNode'])->disableOriginalConstructor()->getMock();
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

        $this->mockQueryResult->expects($this->once())->method('searchHitForNode')->willReturn($hitArray);

        $result = $this->viewHelper->render($this->mockQueryResult, $this->mockNode);
        $this->assertEquals($hitArray, $result, 'The full hit array will be returned');
    }

    /**
     * @test
     */
    public function viewHelperWillReturnAPathFromHitArray()
    {
        $path = 'sort';
        $hitArray = [
            'foo' => 'bar',
            $path => [
                0 => '14',
                1 => '18'
            ]
        ];

        $this->mockQueryResult->expects($this->once())->method('searchHitForNode')->willReturn($hitArray);

        $result = $this->viewHelper->render($this->mockQueryResult, $this->mockNode, $path);
        $this->assertEquals($hitArray[$path], $result, 'Just a path from the full hit array will be returned');
    }

    /**
     * @test
     */
    public function aSingleValueWillBeReturnedForADottedPath()
    {
        $singleValue = 'bar';
        $hitArray = [
            'foo' => [
                0 => $singleValue
            ],
            'sort' => [
                0 => '14',
                0 => '14',
                1 => '18'
            ]
        ];

        $this->mockQueryResult->expects($this->exactly(2))->method('searchHitForNode')->willReturn($hitArray);

        $singleResult = $this->viewHelper->render($this->mockQueryResult, $this->mockNode, 'foo.0');
        $this->assertEquals($singleValue, $singleResult, 'Only a single value will be returned if path is dotted');

        $fullResult = $this->viewHelper->render($this->mockQueryResult, $this->mockNode, 'sort');
        $this->assertEquals($hitArray['sort'], $fullResult, 'Full array will be returned if there are multiple values');
    }
}