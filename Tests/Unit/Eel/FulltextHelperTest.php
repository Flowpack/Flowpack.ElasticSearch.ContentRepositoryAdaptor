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
class FulltextHelperTest extends \TYPO3\Flow\Tests\UnitTestCase {

	/**
	 * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\FulltextHelper
	 */
	protected $helper;

	public function setUp() {
		$this->helper = new \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\FulltextHelper();
	}

	/**
	 * @test
	 */
	public function extractHtmlTagsWorks() {
		$input = 'So.. I want to know... <h2>How do you feel?</h2>This is <p><b>some</b>Text.<h2>I Feel so good</h2>... so good...</p>';
		$expected = array(
			'text' => 'So.. I want to know... This is some Text. ... so good... ',
			'h2' => ' How do you feel? I Feel so good '
		);

		$this->assertSame($expected, $this->helper->extractHtmlTags($input));
	}
}