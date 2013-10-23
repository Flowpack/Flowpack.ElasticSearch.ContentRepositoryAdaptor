<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\FlowQuery;


/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch.ContentRepositoryAdaptor". *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use TYPO3\Flow\Annotations as Flow;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\NodeSearchService;
use TYPO3\Eel\FlowQuery\FlowQuery;
use TYPO3\Eel\FlowQuery\Operations\AbstractOperation;

/**
 * Abstract operation which provides all the basics for an Elastic Search based FlowQuery operation
 */
abstract class AbstractElasticSearchOperation extends AbstractOperation {

	/**
	 * @Flow\Inject
	 * @var NodeSearchService
	 */
	protected $nodeSearchService;

}
