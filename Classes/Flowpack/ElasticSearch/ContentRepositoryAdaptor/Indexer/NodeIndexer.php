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
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Mapping\NodeTypeMappingBuilder;
use Flowpack\ElasticSearch\Domain\Factory\ClientFactory;
use Flowpack\ElasticSearch\Domain\Model\Client;
use Flowpack\ElasticSearch\Domain\Model\Document as ElasticSearchDocument;
use Flowpack\ElasticSearch\Domain\Model\GenericType;
use Flowpack\ElasticSearch\Domain\Model\Index;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeData;
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
	 * the default context variables available inside Eel
	 *
	 * @var array
	 */
	protected $defaultContextVariables;

	/**
	 * @var \TYPO3\Eel\CompilingEvaluator
	 * @Flow\Inject
	 */
	protected $eelEvaluator;

	/**
	 * The default configuration for a given property type in NodeTypes.yaml, if no explicit elasticSearch section defined there.
	 *
	 * @var array
	 */
	protected $defaultConfigurationPerType;

	/**
	 * @param array $settings
	 */
	public function injectSettings(array $settings) {
		$this->indexName = $settings['indexName'];
		$this->defaultConfigurationPerType = $settings['defaultConfigurationPerType'];
		$this->settings = $settings;
	}

	/**
	 * Initialization
	 *
	 * @return void
	 */
	public function initializeObject() {
		$this->searchClient = $this->clientFactory->create();
		$this->nodeIndex = $this->searchClient->findIndex($this->indexName);
	}

	/**
	 * @param NodeData $nodeData
	 * @throws \Exception
	 * @return string
	 */
	public function indexNode(NodeData $nodeData) {
		$persistenceObjectIdentifier = $this->persistenceManager->getIdentifierByObject($nodeData);

		$mappingType = new GenericType($this->nodeIndex, NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($nodeData->getNodeType()));

		if ($nodeData->isRemoved()) {
			$mappingType->deleteDocumentById($persistenceObjectIdentifier);
			$this->systemLogger->log(sprintf('NodeIndexer: Removed node %s from index (node flagged as removed). Persistence ID: %s', $nodeData->getContextPath(), $persistenceObjectIdentifier), LOG_DEBUG, NULL, 'ElasticSearch (CR)');
		}

		$nodePropertiesToBeStoredInElasticSearchIndex = array();

		foreach ($nodeData->getNodeType()->getProperties() as $propertyName => $propertyConfiguration) {

			if (isset($propertyConfiguration['elasticSearch']) && isset($propertyConfiguration['elasticSearch']['indexing'])) {
				if ($propertyConfiguration['elasticSearch']['indexing'] !== '') {
					$nodePropertiesToBeStoredInElasticSearchIndex[$propertyName] = $this->evaluateEelExpression($propertyConfiguration['elasticSearch']['indexing'], $nodeData, $propertyName, ($nodeData->hasProperty($propertyName) ? $nodeData->getProperty($propertyName) : NULL), $persistenceObjectIdentifier);
				}

			} elseif (isset($propertyConfiguration['type']) && isset($this->defaultConfigurationPerType[$propertyConfiguration['type']]['indexing'])) {

				if ($this->defaultConfigurationPerType[$propertyConfiguration['type']]['indexing'] !== '') {
					$nodePropertiesToBeStoredInElasticSearchIndex[$propertyName] = $this->evaluateEelExpression($this->defaultConfigurationPerType[$propertyConfiguration['type']]['indexing'], $nodeData, $propertyName, ($nodeData->hasProperty($propertyName) ? $nodeData->getProperty($propertyName) : NULL), $persistenceObjectIdentifier);
				}

			} else {
				$this->systemLogger->log(sprintf('NodeIndexer (%s) - Property "%s" not indexed because no configuration found.', $persistenceObjectIdentifier, $propertyName), LOG_DEBUG, NULL, 'ElasticSearch (CR)');
			}
		}

		$document = new ElasticSearchDocument($mappingType,
			$nodePropertiesToBeStoredInElasticSearchIndex,
			$persistenceObjectIdentifier
		);
		$document->store();

		$this->systemLogger->log(sprintf('NodeIndexer: Added / updated node %s. Persistence ID: %s', $nodeData->getContextPath(), $persistenceObjectIdentifier), LOG_DEBUG, NULL, 'ElasticSearch (CR)');
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
	 * Evaluate an Eel expression.
	 *
	 * TODO: REFACTOR TO Eel package (as this is copy/pasted from TypoScript Runtime)
	 *
	 * @param string $expression The Eel expression to evaluate
	 * @param NodeData $node
	 * @param string $propertyName
	 * @param mixed $value
	 * @param string $persistenceObjectIdentifier
	 * @return mixed The result of the evaluated Eel expression
	 * @throws Exception
	 */
	protected function evaluateEelExpression($expression, NodeData $node, $propertyName, $value, $persistenceObjectIdentifier) {
		$matches = NULL;
		if (preg_match(\TYPO3\Eel\Package::EelExpressionRecognizer, $expression, $matches)) {
			$contextVariables = array_merge($this->getDefaultContextVariables(), array(
				'node' => $node,
				'propertyName' => $propertyName,
				'value' => $value,
				'persistenceObjectIdentifier' => $persistenceObjectIdentifier
			));

			$context = new \TYPO3\Eel\Context($contextVariables);

			$value = $this->eelEvaluator->evaluate($matches['exp'], $context);
			return $value;
		} else {
			throw new Exception('The Indexing Eel expression "' . $expression . '" used to index property "' . $propertyName . '" of "' . $node->getNodeType()->getName() . '" was not a valid Eel expression. Perhaps you forgot to wrap it in ${...}?', 1383635796);
		}
	}

	/**
	 * Get variables from configuration that should be set in the context by default.
	 * For example Eel helpers are made available by this.
	 *
	 * TODO: REFACTOR TO Eel package (as this is copy/pasted from TypoScript Runtime
	 *
	 * @return array Array with default context variable objects.
	 */
	protected function getDefaultContextVariables() {
		if ($this->defaultContextVariables === NULL) {
			$this->defaultContextVariables = array();
			if (isset($this->settings['defaultContext']) && is_array($this->settings['defaultContext'])) {
				foreach ($this->settings['defaultContext'] as $variableName => $objectType) {
					$currentPathBase = &$this->defaultContextVariables;
					$variablePathNames = explode('.', $variableName);
					foreach ($variablePathNames as $pathName) {
						if (!isset($currentPathBase[$pathName])) {
							$currentPathBase[$pathName] = array();
						}
						$currentPathBase = &$currentPathBase[$pathName];
					}
					$currentPathBase = new $objectType();
				}
			}
		}
		return $this->defaultContextVariables;
	}
}