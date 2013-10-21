<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service;

/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch.ContentRepositoryAdaptor". *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Domain\Model\NodeType;
use TYPO3\Flow\Annotations as Flow;
use Flowpack\ElasticSearch\Domain\Model\Document as ElasticSearchDocument;

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\ElasticSearch;


/**
 * Indexing aspect
 *
 * @Flow\Scope("singleton")
 */
class IndexerService {


	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Persistence\PersistenceManagerInterface
	 */
	protected $persistenceManager;

	/**
	 * @var \Flowpack\ElasticSearch\Domain\Model\Client
	 */
	protected $searchClient;

	/**
	 * @Flow\Inject
	 * @var \Flowpack\ElasticSearch\Domain\Factory\ClientFactory
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
	 * Initializes the searchClient and connects to the 'neos' Index
	 */
	public function initializeObject() {
		$this->searchClient = $this->clientFactory->create();
		$index = $this->searchClient->findIndex('neos');
		$this->nodeType = new NodeType($index);
	}

	/**
	 * @param \TYPO3\TYPO3CR\Domain\Model\NodeData $nodeData
	 * @return string
	 */
	public function updateNodeToIndex(\TYPO3\TYPO3CR\Domain\Model\NodeData $nodeData) {

		$persistenceObjectIdentifier = $this->persistenceManager->getIdentifierByObject($nodeData);

		$document = new ElasticSearchDocument($this->nodeType, array(
				'persistenceObjectIdentifier' => $persistenceObjectIdentifier,
				'workspace' => $nodeData->getWorkspace()->getName(),
				'path' => $nodeData->getPath(),
				'identifier' => $nodeData->getIdentifier(),
				'properties' => $nodeData->getProperties(),
				'nodeType' => $nodeData->getNodeType()->getName(),
				'isRemoved' => $nodeData->isRemoved(),
				'isHidden' => $nodeData->isHidden(),
				'accessRoles' => $nodeData->getAccessRoles(),
				'hiddenBeforeDateTime' => $nodeData->getHiddenBeforeDateTime(),
				'hiddenAfterDateTime' => $nodeData->getHiddenAfterDateTime(),
				'accessRoles' => $nodeData->getAccessRoles(),
				'parentPath' => $nodeData->getParentPath(),


			),
			$persistenceObjectIdentifier
		);
		$document->store();

		//$this->systemLogger->log('updateNodeToIndex', LOG_DEBUG, $document,  'Flowpack\ElasticSearch' , 'IndexerService', 'updateNodeToIndex');
	}

	/**
	 * @param \TYPO3\TYPO3CR\Domain\Model\NodeData $nodeData
	 * @return string
	 */
	public function removeNodeFromIndex(\TYPO3\TYPO3CR\Domain\Model\NodeData $nodeData) {

		$this->systemLogger->log('removeNodeFromIndex', LOG_DEBUG, $nodeData,  'Flowpack\ElasticSearch' , 'IndexerAspect', 'removeNodeFromIndex');
		$persistenceObjectIdentifier = $this->persistenceManager->getIdentifierByObject($nodeData);
		$document = new ElasticSearchDocument($this->type, array(),$persistenceObjectIdentifier);
		$document->remove();
	}
}