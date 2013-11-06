<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional\Eel;

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
 * Functional Testcase for ElasticSearchHelper
 */
class ElasticSearchHelperTest extends \TYPO3\Flow\Tests\FunctionalTestCase {

	/**
	 * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchHelper
	 */
	protected $helper;

	public function setUp() {
		$this->helper = new \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchHelper();
		parent::setUp();
	}

	/**
	 * @test
	 */
	public function extractNodeTypesAndSupertypesWorks() {
		/* @var $nodeTypeManager \TYPO3\TYPO3CR\Domain\Service\NodeTypeManager */
		$nodeTypeManager = $this->objectManager->get('TYPO3\TYPO3CR\Domain\Service\NodeTypeManager');
		$nodeType = $nodeTypeManager->getNodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Type3');

		$expected = array(
			'Flowpack.ElasticSearch.ContentRepositoryAdaptor:Type3',
			'Flowpack.ElasticSearch.ContentRepositoryAdaptor:Type1',
			'Flowpack.ElasticSearch.ContentRepositoryAdaptor:BaseType',
			'Flowpack.ElasticSearch.ContentRepositoryAdaptor:Type2'
		);

		$actual = $this->helper->extractNodeTypeNamesAndSupertypes($nodeType);
		$this->assertSame($expected, $actual);
	}
}