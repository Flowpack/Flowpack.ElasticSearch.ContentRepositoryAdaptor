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

/**
 * Testcase for ElasticSearchHelper
 */
class ElasticSearchHelperTest extends \TYPO3\Flow\Tests\UnitTestCase {

	/**
	 * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchHelper
	 */
	protected $helper;

	public function setUp() {
		$this->helper = new \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchHelper();
	}

	/**
	 * @test
	 */
	public function buildAllPathPrefixesWorksWithRelativePaths() {
		$input = 'foo/bar/baz/testing';
		$expected = array(
			'foo',
			'foo/bar',
			'foo/bar/baz',
			'foo/bar/baz/testing',
		);

		$this->assertSame($expected, $this->helper->buildAllPathPrefixes($input));
	}

	/**
	 * @test
	 */
	public function buildAllPathPrefixesWorksWithAbsolutePaths() {
		$input = '/foo/bar/baz/testing';
		$expected = array(
			'/foo',
			'/foo/bar',
			'/foo/bar/baz',
			'/foo/bar/baz/testing',
		);

		$this->assertSame($expected, $this->helper->buildAllPathPrefixes($input));
	}

	/**
	 * @test
	 */
	public function buildAllPathPrefixesWorksWithEdgeCase() {
		$input = '/';
		$expected = array(
			'/'
		);

		$this->assertSame($expected, $this->helper->buildAllPathPrefixes($input));
	}
}