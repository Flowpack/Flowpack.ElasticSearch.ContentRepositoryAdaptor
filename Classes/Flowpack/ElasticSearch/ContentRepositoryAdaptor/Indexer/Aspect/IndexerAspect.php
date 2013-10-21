<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\Aspect;

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

/**
 * Indexing aspect
 *
 * @Flow\Aspect
 */
class IndexerAspect {

	/**
	 * @Flow\Inject
	 * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\IndexerService
	 */
	protected $indexerService;

	/**
	 * @var \TYPO3\Flow\Log\SystemLoggerInterface
	 * @Flow\Inject
	 */
	protected $systemLogger;

	/**
	 * @Flow\Before("method(TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository->update())")
	 * @param \TYPO3\Flow\Aop\JoinPointInterface $joinPoint
	 * @return string
	 */
	public function updateNodeAspect(\TYPO3\Flow\Aop\JoinPointInterface $joinPoint) {
		$node = $joinPoint->getMethodArgument('object');
		$this->indexerService->updateNodeToIndex($node);
	}

	/**
	 * @Flow\Before("method(TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository->remove())")
	 * @param \TYPO3\Flow\Aop\JoinPointInterface $joinPoint
	 * @return string
	 */
	public function removeNodeAspect(\TYPO3\Flow\Aop\JoinPointInterface $joinPoint) {
		$node = $joinPoint->getMethodArgument('object');
		$this->indexerService->removeNodeFromIndex($node);
	}
}