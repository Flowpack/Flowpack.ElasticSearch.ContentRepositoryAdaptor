<?php
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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryResult;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ViewHelpers\GetHitArrayForNodeViewHelper;
use Neos\ContentRepository\Domain\Model\NodeInterface;

/**
 * Testcase for ElasticSearchQueryBuilder
 */
class GetHitArrayForNodeViewHelperTest extends \Neos\Flow\Tests\UnitTestCase
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
