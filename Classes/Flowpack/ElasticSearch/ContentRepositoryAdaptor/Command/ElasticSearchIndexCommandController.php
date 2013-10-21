<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch".*
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 *  of the License, or (at your option) any later version.                *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;


/**
 * Provides CLI features for index handling
 *
 * @Flow\Scope("singleton")
 */
class ElasticSearchIndexCommandController extends \TYPO3\Flow\Cli\CommandController {

	/**
	 * @Flow\Inject
	 * @var \TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository
	 */
	protected $nodeDataRepository;

	/**
	 * @Flow\Inject
	 * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\IndexerService
	 */
	protected $indexerService;

	/**
	 * indexes all nodes
	 * @param int $limit
	 */
	public function buildIndexCommand($limit = 1000) {
		$this->outputLine('ElasticSearch index is filled with limit ' . $limit);

		$nodeList = $this->nodeDataRepository->findAll();
		foreach ($nodeList as $node) {
			if ($limit > 0){
				$this->indexerService->updateNodeToIndex($node);
				$limit = $limit - 1;
				$this->outputLine($node->getName() . ' was indexed');
			}
		}
	}

	/**
	 * Deletes the full index
	 */
	public function deleteIndexCommand() {
		$this->outputLine('index delete');
	}

}