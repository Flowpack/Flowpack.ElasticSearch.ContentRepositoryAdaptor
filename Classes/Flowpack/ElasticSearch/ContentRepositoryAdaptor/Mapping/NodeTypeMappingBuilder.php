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

use Flowpack\ElasticSearch\Domain\Model\Index;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Flowpack\ElasticSearch\Mapping\MappingCollection;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeType;
use TYPO3\TYPO3CR\Domain\Service\NodeTypeManager;

/**
 * Builds the mapping information for TYPO3CR Node Types in Elastic Search
 *
 * @Flow\Scope("singleton")
 */
class NodeTypeMappingBuilder {

	/**
	 * The default configuration for a given property type in NodeTypes.yaml, if no explicit elasticSearch section defined there.
	 *
	 * @var array
	 */
	protected $defaultConfigurationPerType;

	/**
	 * @Flow\Inject
	 * @var NodeTypeManager
	 */
	protected $nodeTypeManager;

	/**
	 * @var \TYPO3\Flow\Error\Result
	 */
	protected $lastMappingErrors;

	/**
	 * @param array $settings
	 */
	public function injectSettings(array $settings) {
		$this->defaultConfigurationPerType = $settings['defaultConfigurationPerType'];
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
	 * @param \Flowpack\ElasticSearch\Domain\Model\Index $index
	 * @return \Flowpack\ElasticSearch\Mapping\MappingCollection<\Flowpack\ElasticSearch\Domain\Model\Mapping>
	 */
	public function buildMappingInformation(Index $index) {
		$this->lastMappingErrors = new \TYPO3\Flow\Error\Result();

		$mappings = new MappingCollection(MappingCollection::TYPE_ENTITY);

		/** @var NodeType $nodeType */
		foreach ($this->nodeTypeManager->getNodeTypes() as $nodeTypeName => $nodeType) {
			if ($nodeTypeName === 'unstructured' || $nodeType->isAbstract()) {
				continue;
			}

			$type = $index->findType(self::convertNodeTypeNameToMappingName($nodeTypeName));
			$mapping = new Mapping($type);

			foreach ($nodeType->getProperties() as $propertyName => $propertyConfiguration) {
				if (isset($propertyConfiguration['elasticSearch']) && isset($propertyConfiguration['elasticSearch']['mapping'])) {

					if (is_array($propertyConfiguration['elasticSearch']['mapping'])) {
						$mapping->setPropertyByPath($propertyName, $propertyConfiguration['elasticSearch']['mapping']);
					}

				} elseif (isset($propertyConfiguration['type']) && isset($this->defaultConfigurationPerType[$propertyConfiguration['type']]['mapping'])) {

					if (is_array($this->defaultConfigurationPerType[$propertyConfiguration['type']]['mapping'])) {
						$mapping->setPropertyByPath($propertyName, $this->defaultConfigurationPerType[$propertyConfiguration['type']]['mapping']);
					}

				} else {
					$this->lastMappingErrors->addWarning(new \TYPO3\Flow\Error\Warning('Node Type "' . $nodeTypeName . '" - property "' . $propertyName . '": No ElasticSearch Mapping found.'));
				}
			}

			$mappings->add($mapping);
		}

		return $mappings;
	}

	/**
	 * @return \TYPO3\Flow\Error\Result
	 */
	public function getLastMappingErrors() {
		return $this->lastMappingErrors;
	}
}

