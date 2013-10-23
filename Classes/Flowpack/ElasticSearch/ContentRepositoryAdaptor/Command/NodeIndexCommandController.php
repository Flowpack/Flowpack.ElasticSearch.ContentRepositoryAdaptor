<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Command;

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
use TYPO3\Flow\Cli\CommandController;

/**
 * Provides CLI features for index handling
 *
 * @Flow\Scope("singleton")
 */
class NodeIndexCommandController extends CommandController {

	/**
	 * @var string
	 */
	protected $indexName;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository
	 */
	protected $nodeDataRepository;

	/**
	 * @Flow\Inject
	 * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer
	 */
	protected $nodeIndexer;

	/**
	 * @var \TYPO3\Flow\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * @param array $settings
	 */
	public function injectSettings(array $settings) {
		$this->indexName = $settings['indexName'];
	}

	/**
	 * (Re-)index all nodes
	 *
	 * This command indexes (or re-indexes) all nodes contained in the content repository. If the --drop-index flag is
	 * set, any existing node index will be deleted before indexing.
	 *
	 * @param integer $limit Amount of nodes to index at maximum
	 * @return void
	 */
	public function buildCommand($limit = NULL) {
		$this->logger->log(sprintf('Indexing %snodes ... ', ($limit !== NULL ? 'the first ' . $limit . ' ' : '')), LOG_INFO);

		$count = 0;
		foreach ($this->nodeDataRepository->findAll() as $nodeData) {
			if ($limit !== NULL && $count > $limit) {
				break;
			}
			$this->nodeIndexer->indexNode($nodeData);
			$this->logger->log(sprintf('  %s: %s', $nodeData->getWorkspace()->getName(), $nodeData->getPath()), LOG_DEBUG);
			$count ++;
		}

		$this->logger->log('Done.', LOG_INFO);
	}

	/**
	 * Deletes the node index
	 *
	 * This command deletes the whole node index.
	 *
	 * @return void
	 */
	public function deleteCommand() {
		$this->nodeIndexer->deleteIndex();
		$this->logger->log(sprintf('Deleted the node index "%s". ', $this->indexName), LOG_INFO);
	}

}