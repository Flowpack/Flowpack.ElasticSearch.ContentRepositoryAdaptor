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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Mapping\NodeTypeMappingBuilder;
use Flowpack\ElasticSearch\Domain\Model\GenericType;
use Flowpack\ElasticSearch\Domain\Model\Index;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Flowpack\ElasticSearch\Mapping\MappingCollection;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeData;
use Flowpack\ElasticSearch\Domain\Factory\ClientFactory;
use Flowpack\ElasticSearch\Domain\Model\Client;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Domain\Model\NodeType;
use Flowpack\ElasticSearch\Domain\Model\Document as ElasticSearchDocument;
use TYPO3\TYPO3CR\Domain\Service\NodeTypeManager;


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
	 * @var Index
	 */
	protected $nodeIndex;

	/**
	 * @var Client
	 */
	protected $searchClient;

	/**
	 * @var MappingCollection
	 */
	protected $mappings;

	/**
	 * @Flow\Inject
	 * @var ClientFactory
	 */
	protected $clientFactory;

	/**
	 * @Flow\Inject
	 * @var NodeTypeMappingBuilder
	 */
	protected $nodeTypeMappingBuilder;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Persistence\PersistenceManagerInterface
	 */
	protected $persistenceManager;

	/**
	 * @Flow\Inject
	 * @var NodeTypeManager
	 */
	protected $nodeTypeManager;

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
	 * Initialization
	 *
	 * @return void
	 */
	public function initializeObject() {
		$this->searchClient = $this->clientFactory->create();
		$this->nodeIndex = $this->searchClient->findIndex($this->indexName);
		$this->mappings = $this->nodeTypeMappingBuilder->buildMappingInformation();
	}

	/**
	 * @param NodeData $nodeData
	 * @return string
	 */
	public function indexNode(NodeData $nodeData) {
		$persistenceObjectIdentifier = $this->persistenceManager->getIdentifierByObject($nodeData);

		$mappingType = new GenericType($this->nodeIndex, NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($nodeData->getNodeType()));

		if ($nodeData->isRemoved()) {
			$mappingType->deleteDocumentById($persistenceObjectIdentifier);
			$this->systemLogger->log(sprintf('NodeIndexer: Removed node %s from index (node flagged as removed). Persistence ID: %s', $nodeData->getContextPath(), $persistenceObjectIdentifier), LOG_DEBUG, NULL, 'ElasticSearch (CR)');
		}

		$convertedNodeProperties = array();
		foreach ($nodeData->getProperties() as $propertyName => $propertyValue) {

			// FIXME: The MappingCollection of the ES package needs to be refactored / is too entity specific and needs
			// a way to query mappings for a specific (node) type:
			$foundMapping = NULL;
			foreach ($this->mappings as $mapping) {
				/** @var Mapping $mapping */
				if ($mapping->getType()->getName() === NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($nodeData->getNodeType())) {
					$foundMapping = $mapping;
				}
			}

			// FIXME: Cleanup handling of unstructured content:
			if ($foundMapping === NULL) {
				if (NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($nodeData->getNodeType()) === 'unstructured') {
					$convertedNodeProperties[$propertyName] = $propertyValue;
				} else {
					throw new \Exception('Did not find Elastic Search mapping for node type ' . $nodeData->getNodeType(), 1382511542);
				}
			} else {
				$convertedNodeProperties[$propertyName] = $this->convertProperty($foundMapping->getPropertyByPath('properties.properties.' . $propertyName)['type'], $propertyValue);
			}

		}

		$document = new ElasticSearchDocument($mappingType,
			array(
				'persistenceObjectIdentifier' => $persistenceObjectIdentifier,
				'identifier' => $nodeData->getIdentifier(),
				'workspace' => $nodeData->getWorkspace()->getName(),
				'path' => $nodeData->getPath(),
				'parentPath' => $nodeData->getParentPath(),
				'sortIndex' => $nodeData->getIndex(),
				'properties' => $convertedNodeProperties,
				'hidden' => $nodeData->isHidden(),
				'hiddenBeforeDateTime' => $this->convertProperty('date', $nodeData->getHiddenBeforeDateTime()),
				'hiddenAfterDateTime' =>  $this->convertProperty('date', $nodeData->getHiddenAfterDateTime()),
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
		$this->nodeIndex->request('DELETE', '/' . NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($nodeData->getNodeType()) . '/' . $persistenceObjectIdentifier);

		$this->systemLogger->log(sprintf('NodeIndexer: Removed node %s from index (node actually removed). Persistence ID: %s', $nodeData->getContextPath(), $persistenceObjectIdentifier), LOG_DEBUG, NULL, 'ElasticSearch (CR)');
	}

	/**
	 * Removes the whole node index
	 *
	 * @return void
	 */
	public function deleteIndex() {
		$this->nodeIndex->delete();
	}

	/**
	 * Converts the given property value into a format which is suitable for Elastic Search.
	 *
	 * @param string $type The Elastic Search type to convert to
	 * @param mixed $value The value to convert
	 * @return string The converted value
	 */
	protected function convertProperty($type, $value) {
		switch ($type) {
			case 'date':
				if (!$value instanceof \DateTime) {
					$value = new \DateTime($value);
				}
				return $value->format('c');
			case 'boolean':
				return ($value) ? 'T' : 'F';
			break;
			case 'string':
			default:
				if (is_object($value)) {
					return '<object>';
				}
				return (string)$value;
		}
	}
}