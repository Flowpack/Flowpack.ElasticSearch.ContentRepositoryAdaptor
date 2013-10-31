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

use TYPO3\Eel\FlowQuery\FlowQuery;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

/**
 * "findRecent()" operation working on TYPO3CR nodes stored in Elastic Search documents. This operation allows for retrieval
 * of nodes specified by a path. The current context node is also used as a context for evaluating relative paths.
 */
class FindRecentOperation extends AbstractElasticSearchOperation {

	/**
	 * {@inheritdoc}
	 *
	 * @var string
	 */
	static protected $shortName = 'findRecent';

	/**
	 * {@inheritdoc}
	 *
	 * @var integer
	 */
	static protected $priority = 200;

	/**
	 * {@inheritdoc}
	 *
	 * @param array (or array-like object) $context onto which this operation should be applied
	 * @return boolean TRUE if the operation can be applied onto the $context, FALSE otherwise
	 */
	public function canEvaluate($context) {
		return (isset($context[0]) && ($context[0] instanceof NodeInterface));
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param FlowQuery $flowQuery the FlowQuery object
	 * @param array $arguments the arguments for this operation
	 * @return void
	 */
	public function evaluate(FlowQuery $flowQuery, array $arguments) {
		$context = $flowQuery->getContext();
		if (!isset($context[0]) || count($arguments) < 2) {
			return NULL;
		}
		/** @var \TYPO3\TYPO3CR\Domain\Model\NodeInterface $contextNode */
		$contextNode = $context[0];

		// TODO: Implement real Fizzle support:
		$nodeTypeFilter = NULL;
		$fizzleStatement = $arguments[0];
		if (strpos($fizzleStatement, '[instanceof ') === 0) {
			$nodeTypeFilter = substr($fizzleStatement, 12, -1);
		}

		$dateTimeFieldName = $arguments[1];
		$maximumResults = isset($arguments[2]) ? intval($arguments[2]) : 1;
		$fromResult = isset($arguments[3]) ? intval($arguments[3]) : NULL;

		$nodes = $this->nodeSearchService->findRecent($contextNode->getPath(), $dateTimeFieldName, $maximumResults, $fromResult, $nodeTypeFilter, $contextNode->getContext());
		$flowQuery->setContext($nodes);
	}
}
