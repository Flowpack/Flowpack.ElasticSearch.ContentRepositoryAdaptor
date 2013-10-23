<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Mapping;

/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch.ContentRepositoryAdaptor". *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use Flowpack\ElasticSearch\Domain\Model\GenericType;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use TYPO3\Flow\Annotations as Flow;
use Flowpack\ElasticSearch\Domain\Factory\ClientFactory;
use Flowpack\ElasticSearch\Domain\Model\Client;
use Flowpack\ElasticSearch\Domain\Model\Index;
use Flowpack\ElasticSearch\Mapping\MappingCollection;
use TYPO3\Flow\Utility\TypeHandling;
use TYPO3\TYPO3CR\Domain\Model\NodeType;
use TYPO3\TYPO3CR\Domain\Service\NodeTypeManager;

/**
 * Builds the mapping information for TYPO3CR Node Types in Elastic Search
 *
 * @Flow\Scope("singleton")
 */
class NodeTypeMappingBuilder {

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
	 * @Flow\Inject
	 * @var ClientFactory
	 */
	protected $clientFactory;

	/**
	 * @Flow\Inject
	 * @var NodeTypeManager
	 */
	protected $nodeTypeManager;

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
		$this->nodeIndex = $this->searchClient->findIndex($this->indexName);
	}

	/**
	 * Converts a TYPO3CR Node Type name into a name which can be used for an Elastic Search Mapping
	 *
	 * @param string $nodeTypeName
	 * @return string
	 */
	static public function convertNodeTypeNameToMappingName($nodeTypeName) {
		return str_replace('.', '-', $nodeTypeName);
	}

	/**
	 * Builds a Mapping Collection from the configured node types
	 *
	 * @return \Flowpack\ElasticSearch\Mapping\MappingCollection<\Flowpack\ElasticSearch\Domain\Mapping>
	 */
	public function buildMappingInformation() {
		$response = $this->searchClient->request('HEAD', '/' . $this->indexName);
		if ($response->getStatusCode() === 404) {
			$this->searchClient->request('PUT', '/' . $this->indexName);
		}

		$mappings = new MappingCollection(MappingCollection::TYPE_ENTITY);

		foreach ($this->nodeTypeManager->getNodeTypes() as $nodeTypeName => $nodeType) {
			if ($nodeTypeName === 'unstructured' || $nodeType->isAbstract()) {
				continue;
			}

			/** @var NodeType $nodeType */
			$type = new GenericType($this->nodeIndex, self::convertNodeTypeNameToMappingName($nodeTypeName));
			$mapping = new Mapping($type);

			$mapping->setPropertyByPath('persistenceObjectIdentifier', array('type' => 'string', 'index' => 'not_analyzed'));
			$mapping->setPropertyByPath('identifier', array('type' => 'string', 'index' => 'not_analyzed'));
			$mapping->setPropertyByPath('workspace', array('type' => 'string', 'index' => 'not_analyzed'));
			$mapping->setPropertyByPath('path', array('type' => 'string', 'index' => 'not_analyzed'));
			$mapping->setPropertyByPath('parentPath', array('type' => 'string', 'index' => 'not_analyzed'));
			$mapping->setPropertyByPath('sortIndex', array('type' => 'integer'));
			$mapping->setPropertyByPath('hidden', array('type' => 'boolean'));

			foreach ($nodeType->getDeclaredSuperTypes() as $superNodeType) {
				/** @var NodeType $superNodeType */
				foreach ($superNodeType->getProperties() as $propertyName => $propertyDefinition) {
					$this->augmentMappingByProperty($mapping, $propertyName, $propertyDefinition);
				}
			}
			foreach ($nodeType->getProperties() as $propertyName => $propertyDefinition) {
				$this->augmentMappingByProperty($mapping, $propertyName, $propertyDefinition);
			}

			$mappings->add($mapping);
		}

		return $mappings;
	}

	/**
	 *
	 *
	 * @param Mapping $mapping
	 * @param string $propertyName
	 * @param array $propertyDefinition
	 * @throws \Flowpack\ElasticSearch\Exception
	 * @return void
	 */
	protected function augmentMappingByProperty(Mapping $mapping, $propertyName, array $propertyDefinition) {
		if (TypeHandling::isSimpleType($propertyDefinition['type']) || $propertyDefinition['type'] === 'date') {
			$mappingType = $propertyDefinition['type'];
		} else {
			$mappingType = 'string';
		}
		$mapping->setPropertyByPath('properties.properties.' . $propertyName, array('type' => $mappingType));
	}


}
?>