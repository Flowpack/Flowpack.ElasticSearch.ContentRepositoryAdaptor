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

/**
 * Testcase for ElasticSearchQueryBuilder
 */
class ElasticSearchQueryBuilderTest extends \TYPO3\Flow\Tests\UnitTestCase {

	/**
	 * @var ElasticSearchQueryBuilder
	 */
	protected $queryBuilder;

	public function setUp() {
		$node = $this->getMock('TYPO3\TYPO3CR\Domain\Model\NodeInterface');
		$node->expects($this->any())->method('getPath')->will($this->returnValue('/foo/bar'));
		$mockContext = $this->getMockBuilder('TYPO3\TYPO3CR\Domain\Service\Context')->disableOriginalConstructor()->getMock();
		$mockContext->expects($this->any())->method('getDimensions')->will($this->returnValue(array()));
		$node->expects($this->any())->method('getContext')->will($this->returnValue($mockContext));

		$mockWorkspace = $this->getMockBuilder('TYPO3\TYPO3CR\Domain\Model\Workspace')->disableOriginalConstructor()->getMock();
		$mockContext->expects($this->any())->method('getWorkspace')->will($this->returnValue($mockWorkspace));

		$mockWorkspace->expects($this->any())->method('getName')->will($this->returnValue('user-foo'));

		$this->queryBuilder = new ElasticSearchQueryBuilder();
		$this->queryBuilder->query($node);
	}

	/**
	 * @test
	 */
	public function basicRequestStructureTakesContextNodeIntoAccount() {
		$expected = array(
			'query' => array (
				'filtered' => array (
					'query' => array (
						'bool' => array(
							'must' => array(
								array('match_all' => array())
							)
						)
					),
					'filter' => array (
						'bool' => array (
							'must' => array (
								0 => array (
									'term' => array (
										'__parentPath' => '/foo/bar'
									)
								),
								1 => array (
									'terms' => array (
										'__workspace' => array('live', 'user-foo')
									)
								)
							),
							'should' => array (),
							'must_not' => array (
								// Filter out all hidden elements
								array(
									'term' => array('_hidden' => TRUE)
								),
								// if now < hiddenBeforeDateTime: HIDE
								// -> hiddenBeforeDateTime > now
								array(
									'range' => array('_hiddenBeforeDateTime' => array(
										'gt' => 'now'
									))
								),
								array(
									'range' => array('_hiddenAfterDateTime' => array(
										'lt' => 'now'
									))
								)
							)
						)
					)
				)
			),
			'fields' => array('__path')
		);
		$actual = $this->queryBuilder->getRequest();
		$this->assertSame($expected, $actual);
	}

	/**
	 * @test
	 * @expectedException \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\QueryBuildingException
	 */
	public function queryFilterThrowsExceptionOnInvalidClauseType() {
		$this->queryBuilder->queryFilter('foo', array(), 'unsupported');
	}

	/**
	 * @test
	 */
	public function nodeTypeFilterWorks() {
		$this->queryBuilder->nodeType('Foo.Bar:Baz');
		$expected = array(
			'term' => array(
				'__typeAndSupertypes' => 'Foo.Bar:Baz'
			)
		);
		$actual = $this->queryBuilder->getRequest();
		$this->assertInArray($expected, $actual['query']['filtered']['filter']['bool']['must']);
	}

	/**
	 * @test
	 */
	public function sortAscWorks() {
		$this->queryBuilder->sortAsc('fieldName');
		$expected = array(
			array(
				'fieldName' => array('order' => 'asc')
			)
		);
		$actual = $this->queryBuilder->getRequest();
		$this->assertSame($expected, $actual['sort']);
	}

	/**
	 * @test
	 */
	public function sortingIsAdditive() {
		$this->queryBuilder->sortAsc('fieldName')->sortDesc('field2')->sortAsc('field3');
		$expected = array(
			array(
				'fieldName' => array('order' => 'asc')
			),
			array(
				'field2' => array('order' => 'desc')
			),
			array(
				'field3' => array('order' => 'asc')
			)
		);
		$actual = $this->queryBuilder->getRequest();
		$this->assertSame($expected, $actual['sort']);
	}

	/**
	 * @test
	 */
	public function limitWorks() {
		$this->queryBuilder->limit(2);
		$actual = $this->queryBuilder->getRequest();
		$this->assertSame(2, $actual['size']);
	}

	/**
	 * @test
	 */
	public function sortDescWorks() {
		$this->queryBuilder->sortDesc('fieldName');
		$expected = array(
			array(
				'fieldName' => array('order' => 'desc')
			)
		);
		$actual = $this->queryBuilder->getRequest();
		$this->assertSame($expected, $actual['sort']);
	}

	public function rangeConstraintExamples() {
		return array(
			array('greaterThan', 'gt', 10),
			array('greaterThanOrEqual', 'gte', 20),
			array('lessThan', 'lt', 'now'),
			array('lessThanOrEqual', 'lte', 40)
		);
	}

	/**
	 * @test
	 * @dataProvider rangeConstraintExamples
	 */
	public function rangeConstraintsWork($constraint, $operator, $value) {
		$this->queryBuilder->$constraint('fieldName', $value);
		$expected = array(
			'range' => array(
				'fieldName' => array($operator => $value)
			)
		);
		$actual = $this->queryBuilder->getRequest();
		$this->assertInArray($expected, $actual['query']['filtered']['filter']['bool']['must']);
	}

	/**
	 * Test helper
	 *
	 * @param $expected
	 * @param $actual
	 * @return void
	 */
	protected function assertInArray($expected, $actual) {
		foreach ($actual as $actualElement) {
			if ($actualElement === $expected) {
				$this->assertTrue(TRUE);
				return;
			}
		}

		// because $expected !== $actual ALWAYS, this will NEVER match but display a nice error message.
		$this->assertSame($expected, $actual, 'The $expected array was not found inside $actual.');
	}

}