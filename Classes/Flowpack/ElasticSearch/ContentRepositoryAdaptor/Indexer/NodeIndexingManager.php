<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer;

/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch.ContentRepositoryAdaptor". *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use Flowpack\ElasticSearch\Domain\Model\Client;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\Node;

/**
 * Indexer for Content Repository Nodes. Manages an indexing queue to allow for deferred indexing.
 *
 * @Flow\Scope("singleton")
 */
class NodeIndexingManager {

	/**
	 * @var \SplObjectStorage<NodeData>
	 */
	protected $nodesToBeIndexed;

	/**
	 * @var \SplObjectStorage<NodeData>
	 */
	protected $nodesToBeRemoved;

	/**
	 * @var array
	 */
	protected $targetWorkspaceNamesForNodesToBeIndexed = array();

	/**
	 * the indexing batch size (from the settings)
	 *
	 * @var integer
	 */
	protected $indexingBatchSize;

	/**
	 * @Flow\Inject
	 * @var NodeIndexer
	 */
	protected $nodeIndexer;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->nodesToBeIndexed = new \SplObjectStorage();
		$this->nodesToBeRemoved = new \SplObjectStorage();
	}

	/**
	 * @param array $settings
	 */
	public function injectSettings(array $settings) {
		$this->indexingBatchSize = $settings['indexingBatchSize'];
	}

	/**
	 * Schedule a node for indexing
	 *
	 * @param Node $node
	 * @param mixed $targetWorkspace In case this is triggered during publishing, a Workspace will be passed in
	 * @return void
	 */
	public function indexNode(Node $node, $targetWorkspace = NULL) {
		$this->nodesToBeRemoved->detach($node);
		$this->nodesToBeIndexed->attach($node);
		$this->targetWorkspaceNamesForNodesToBeIndexed[$node->getContextPath()] = $targetWorkspace instanceof \TYPO3\TYPO3CR\Domain\Model\Workspace ? $targetWorkspace->getName() : NULL;

		$this->flushQueuesIfNeeded();
	}

	/**
	 * Schedule a node for removal of the index
	 *
	 * @param Node $node
	 * @return void
	 */
	public function removeNode(Node $node) {
		$this->nodesToBeIndexed->detach($node);
		$this->nodesToBeRemoved->attach($node);

		$this->flushQueuesIfNeeded();
	}

	/**
	 * Flush the indexing/removal queues, actually processing them, if the
	 * maximum indexing batch size has been reached.
	 *
	 * @return void
	 */
	protected function flushQueuesIfNeeded() {
		if ($this->nodesToBeIndexed->count() + $this->nodesToBeRemoved->count() > $this->indexingBatchSize) {
			$this->flushQueues();
		}
	}

	/**
	 * Flush the indexing/removal queues, actually processing them.
	 *
	 * @return void
	 */
	public function flushQueues() {
		/** @var \TYPO3\TYPO3CR\Domain\Model\NodeInterface $nodeToBeIndexed  */
		foreach ($this->nodesToBeIndexed as $nodeToBeIndexed) {
			if (!isset($this->targetWorkspaceNamesForNodesToBeIndexed[$nodeToBeIndexed->getContextPath()])) {
				$this->nodeIndexer->indexNode($nodeToBeIndexed);
			} else {
				$this->nodeIndexer->indexNode($nodeToBeIndexed, $this->targetWorkspaceNamesForNodesToBeIndexed[$nodeToBeIndexed->getContextPath()]);
			}
		}

		foreach ($this->nodesToBeRemoved as $nodeToBeRemoved) {
			$this->nodeIndexer->removeNode($nodeToBeRemoved);
		}
		$this->nodeIndexer->flush();
		$this->nodesToBeIndexed = new \SplObjectStorage();
		$this->nodesToBeRemoved = new \SplObjectStorage();
		$this->targetWorkspaceNamesForNodesToBeIndexed = array();
	}
}