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

use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeData;
use Flowpack\ElasticSearch\Domain\Factory\ClientFactory;
use Flowpack\ElasticSearch\Domain\Model\Client;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Domain\Model\NodeType;
use Flowpack\ElasticSearch\Domain\Model\Document as ElasticSearchDocument;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\ElasticSearch;


/**
 * Indexer for Content Repository Nodes
 *
 * @Flow\Scope("singleton")
 */
class NodeIndexer {

	/**
	 * @var string
	 */
	protected $indexName;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Persistence\PersistenceManagerInterface
	 */
	protected $persistenceManager;

	/**
	 * @var Client
	 */
	protected $searchClient;

	/**
	 * @Flow\Inject
	 * @var ClientFactory
	 */
	protected $clientFactory;

	/**
	 * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Domain\Model\NodeType
	 */
	protected $nodeType;

	/**
	 * @var \TYPO3\Flow\Log\SystemLoggerInterface
	 * @Flow\Inject
	 */
	protected $systemLogger;

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @param array $settings
	 */
	public function injectSettings(array $settings) {
		$this->indexName = $settings['indexName'];
	}

	/**
	 * Initializes the searchClient and connects to the Index
	 */
	public function initializeObject() {
		$this->searchClient = $this->clientFactory->create();
		$index = $this->searchClient->findIndex($this->indexName);
		$this->nodeType = new NodeType($index);
	}

	/**
	 * @param NodeData $nodeData
	 * @return string
	 */
	public function indexNode(NodeData $nodeData) {
		$persistenceObjectIdentifier = $this->persistenceManager->getIdentifierByObject($nodeData);

		if ($nodeData->isRemoved()) {
			$this->nodeType->deleteDocumentById($persistenceObjectIdentifier);
			$this->systemLogger->log(sprintf('NodeIndexer: Removed node %s from index (node flagged as removed). Persistence ID: %s', $nodeData->getContextPath(), $persistenceObjectIdentifier), LOG_DEBUG, NULL, 'ElasticSearch (CR)');
		}

		$document = new ElasticSearchDocument($this->nodeType,
			array(
				'persistenceObjectIdentifier' => $persistenceObjectIdentifier,
				'workspace' => $nodeData->getWorkspace()->getName(),
				'path' => $nodeData->getPath(),
				'parentPath' => $nodeData->getParentPath(),
				'identifier' => $nodeData->getIdentifier(),
				'properties' => $nodeData->getProperties(),
				'nodeType' => $nodeData->getNodeType()->getName(),
				'isHidden' => $nodeData->isHidden(),
				'accessRoles' => $nodeData->getAccessRoles(),
				'hiddenBeforeDateTime' => $nodeData->getHiddenBeforeDateTime(),
				'hiddenAfterDateTime' => $nodeData->getHiddenAfterDateTime(),
			),
			$persistenceObjectIdentifier
		);
		$document->store();

		$this->systemLogger->log(sprintf('NodeIndexer: Added /updated node %s. Persistence ID: %s', $nodeData->getContextPath(), $persistenceObjectIdentifier), LOG_DEBUG, NULL, 'ElasticSearch (CR)');
	}

	/**
	 * @param NodeData $nodeData
	 * @return string
	 */
	public function removeNode(NodeData $nodeData) {
		$persistenceObjectIdentifier = $this->persistenceManager->getIdentifierByObject($nodeData);
		$this->nodeType->deleteDocumentById($persistenceObjectIdentifier);

		$this->systemLogger->log(sprintf('NodeIndexer: Removed node %s from index (node actually removed). Persistence ID: %s', $nodeData->getContextPath(), $persistenceObjectIdentifier), LOG_DEBUG, NULL, 'ElasticSearch (CR)');
	}
}