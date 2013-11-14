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
use Flowpack\ElasticSearch\Domain\Model\Client;
use Flowpack\ElasticSearch\Domain\Model\Document as ElasticSearchDocument;
use Flowpack\ElasticSearch\Domain\Model\Index;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeData;
use TYPO3\TYPO3CR\Domain\Service\NodeTypeManager;

/*
 * Yes, dirty as hell. But the function is just too helpful...
 * json_last_error_msg() has been added in PHP 5.5
 */
if (!function_exists('json_last_error_msg')) {
	function json_last_error_msg() {
		static $errors = array(
			JSON_ERROR_NONE => NULL,
			JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
			JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
			JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
			JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
			JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded'
		);
		$error = json_last_error();

		return array_key_exists($error, $errors) ? $errors[$error] : "Unknown error ({$error})";
	}
}

/**
 * Indexer for Content Repository Nodes. Triggered from the NodeIndexingManager.
 *
 * Internally, uses a bulk request.
 *
 * @Flow\Scope("singleton")
 */
class NodeIndexer {

	/**
	 * Optional postfix for the index, e.g. to have different indexes by timestamp.
	 *
	 * @var string
	 */
	protected $indexNamePostfix = '';

	/**
	 * @Flow\Inject
	 * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient
	 */
	protected $searchClient;

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
	 * @Flow\Inject
	 * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\LoggerInterface
	 */
	protected $logger;

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
	 * The current ElasticSearch bulk request, in the format required by http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/docs-bulk.html
	 *
	 * @var array
	 */
	protected $currentBulkRequest = array();

	/**
	 * @param array $settings
	 */
	public function injectSettings(array $settings) {
		$this->defaultConfigurationPerType = $settings['defaultConfigurationPerType'];
		$this->settings = $settings;
	}

	/**
	 * Returns the index name to be used for indexing, with optional indexNamePostfix appended.
	 *
	 * @return string
	 */
	public function getIndexName() {
		$indexName = $this->searchClient->getIndexName();
		if (strlen($this->indexNamePostfix) > 0) {
			$indexName .= '-' . $this->indexNamePostfix;
		}

		return $indexName;
	}

	/**
	 * Set the postfix for the index name
	 *
	 * @param $indexNamePostfix
	 * @return void
	 */
	public function setIndexNamePostfix($indexNamePostfix) {
		$this->indexNamePostfix = $indexNamePostfix;
	}

	/**
	 * Return the currently active index to be used for indexing
	 *
	 * @return Index
	 */
	public function getIndex() {
		return $this->searchClient->findIndex($this->getIndexName());
	}

	/**
	 * index this node, and add it to the current bulk request.
	 *
	 * @param NodeData $nodeData
	 * @throws \Exception
	 * @return string
	 */
	public function indexNode(NodeData $nodeData) {
		$persistenceObjectIdentifier = $this->persistenceManager->getIdentifierByObject($nodeData);
		$nodeType = $nodeData->getNodeType();

		$mappingType = $this->getIndex()->findType(NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($nodeType));

		if ($nodeData->isRemoved()) {
			// TODO: handle deletion from the fulltext index as well
			$mappingType->deleteDocumentById($persistenceObjectIdentifier);
			$this->logger->log(sprintf('NodeIndexer: Removed node %s from index (node flagged as removed). Persistence ID: %s', $nodeData->getContextPath(), $persistenceObjectIdentifier), LOG_DEBUG, NULL, 'ElasticSearch (CR)');

			return;
		}

		$nodePropertiesToBeStoredInElasticSearchIndex = array();
		$fulltextIndexOfNode = array();
		$fulltextIndexingEnabledForNode = $this->isFulltextEnabled($nodeData);

		foreach ($nodeType->getProperties() as $propertyName => $propertyConfiguration) {

			// Property Indexing
			if (isset($propertyConfiguration['elasticSearch']) && isset($propertyConfiguration['elasticSearch']['indexing'])) {
				if ($propertyConfiguration['elasticSearch']['indexing'] !== '') {
					$nodePropertiesToBeStoredInElasticSearchIndex[$propertyName] = $this->evaluateEelExpression($propertyConfiguration['elasticSearch']['indexing'], $nodeData, $propertyName, ($nodeData->hasProperty($propertyName) ? $nodeData->getProperty($propertyName) : NULL), $persistenceObjectIdentifier);
				}
			} elseif (isset($propertyConfiguration['type']) && isset($this->defaultConfigurationPerType[$propertyConfiguration['type']]['indexing'])) {

				if ($this->defaultConfigurationPerType[$propertyConfiguration['type']]['indexing'] !== '') {
					$nodePropertiesToBeStoredInElasticSearchIndex[$propertyName] = $this->evaluateEelExpression($this->defaultConfigurationPerType[$propertyConfiguration['type']]['indexing'], $nodeData, $propertyName, ($nodeData->hasProperty($propertyName) ? $nodeData->getProperty($propertyName) : NULL), $persistenceObjectIdentifier);
				}
			} else {
				$this->logger->log(sprintf('NodeIndexer (%s) - Property "%s" not indexed because no configuration found.', $persistenceObjectIdentifier, $propertyName), LOG_DEBUG, NULL, 'ElasticSearch (CR)');
			}

			if ($fulltextIndexingEnabledForNode === TRUE) {
				if (isset($propertyConfiguration['elasticSearch']) && isset($propertyConfiguration['elasticSearch']['fulltextExtractor'])) {
					if ($propertyConfiguration['elasticSearch']['fulltextExtractor'] !== '') {
						$fulltextExtractionExpression = $propertyConfiguration['elasticSearch']['fulltextExtractor'];

						$fulltextIndexOfProperty = $this->evaluateEelExpression($fulltextExtractionExpression, $nodeData, $propertyName, ($nodeData->hasProperty($propertyName) ? $nodeData->getProperty($propertyName) : NULL), $persistenceObjectIdentifier);

						if (!is_array($fulltextIndexOfProperty)) {
							throw new Exception\IndexingException('The fulltext index for property "' . $propertyName . '" of node "' . $nodeData->getPath() . '" could not be retrieved; the Eel expression "' . $fulltextExtractionExpression . '" is no valid fulltext extraction expression.');
						}

						foreach ($fulltextIndexOfProperty as $bucket => $value) {
							if (!isset($fulltextIndexOfNode[$bucket])) {
								$fulltextIndexOfNode[$bucket] = '';
							}
							$fulltextIndexOfNode[$bucket] .= ' ' . $value;
						}
					}
					// TODO: also allow fulltextExtractor in settings!!
				}
			}
		}

		$document = new ElasticSearchDocument($mappingType,
			$nodePropertiesToBeStoredInElasticSearchIndex,
			$persistenceObjectIdentifier
		);

		$documentData = $document->getData();

		if ($fulltextIndexingEnabledForNode === TRUE) {
			if ($this->isFulltextRoot($nodeData)) {
				// for fulltext root documents, we need to preserve the "__fulltext" field. That's why we use the
				// "update" API instead of the "index" API, with a custom script internally; as we
				// shall not delete the "__fulltext" part of the document if it has any.
				$this->currentBulkRequest[] = array(
					array(
						'update' => array(
							'_type' => $document->getType()->getName(),
							'_id' => $document->getId()
						)
					),
					// http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/docs-update.html
					array(
						'script' => '
							fulltext = (ctx._source.containsKey("__fulltext") ? ctx._source.__fulltext : new LinkedHashMap());
							fulltextParts = (ctx._source.containsKey("__fulltextParts") ? ctx._source.__fulltextParts : new LinkedHashMap());
							ctx._source = newData;
							ctx._source.__fulltext = fulltext;
							ctx._source.__fulltextParts = fulltextParts
						',
						'params' => array(
							'newData' => $documentData
						),
						'upsert' => $documentData
					)
				);
			} else {
				// non-fulltext-root documents can be indexed as-they-are
				$this->currentBulkRequest[] = array(
					array(
						'index' => array(
							'_type' => $document->getType()->getName(),
							'_id' => $document->getId()
						)
					),
					$documentData
				);
			}

			$this->updateFulltext($nodeData, $fulltextIndexOfNode);
		}

		$this->logger->log(sprintf('NodeIndexer: Added / updated node %s. Persistence ID: %s', $nodeData->getContextPath(), $persistenceObjectIdentifier), LOG_DEBUG, NULL, 'ElasticSearch (CR)');
	}

	/**
	 *
	 *
	 * @param NodeData $nodeData
	 * @param array $fulltextIndexOfNode
	 * @return void
	 */
	protected function updateFulltext(NodeData $nodeData, array $fulltextIndexOfNode) {
		if ($nodeData->getWorkspace()->getName() !== 'live' || count($fulltextIndexOfNode) === 0) {
			// fulltext indexing should only be done in live workspace, and if there's something to index
			return;
		}

		$closestFulltextNode = $nodeData;
		while (!$this->isFulltextRoot($closestFulltextNode)) {
			$closestFulltextNode = $closestFulltextNode->getParent();
			if ($closestFulltextNode === NULL) {
				// root of hierarchy, no fulltext root found anymore, abort silently...
				return;
			}
		}

		$this->currentBulkRequest[] = array(
			array(
				'update' => array(
					'_type' => NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($closestFulltextNode->getNodeType()->getName()),
					'_id' => $this->persistenceManager->getIdentifierByObject($closestFulltextNode)
				)
			),
			// http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/docs-update.html
			array(
				// first, update the __fulltextParts, then re-generate the __fulltext from all __fulltextParts
				'script' => '
					if (!ctx._source.containsKey("__fulltextParts")) {
						ctx._source.__fulltextParts = new LinkedHashMap();
					}
					ctx._source.__fulltextParts[identifier] = fulltext;

					ctx._source.__fulltext = new LinkedHashMap();
					foreach (fulltextByNode : ctx._source.__fulltextParts.entrySet()) {
						foreach (element : fulltextByNode.value.entrySet()) {
							ctx._source.__fulltext[element.key] += " " + element.value;
						}
					}
				',
				'params' => array(
					'identifier' => $nodeData->getIdentifier(),
					'fulltext' => $fulltextIndexOfNode
				),
				'upsert' => array(
					'__fulltext' => $fulltextIndexOfNode,
					'__fulltextParts' => array(
						$nodeData->getIdentifier() => $fulltextIndexOfNode
					)
				)
			)
		);
	}

	/**
	 * Whether the node has fulltext indexing enabled.
	 *
	 * @param NodeData $nodeData
	 * @return boolean
	 */
	protected function isFulltextEnabled(NodeData $nodeData) {
		if ($nodeData->getNodeType()->hasElasticSearch()) {
			$elasticSearchSettingsForNode = $nodeData->getNodeType()->getElasticSearch();
			if (isset($elasticSearchSettingsForNode['fulltext']['enable']) && $elasticSearchSettingsForNode['fulltext']['enable'] === TRUE) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Whether the node is configured as fulltext root.
	 *
	 * @param NodeData $nodeData
	 * @return boolean
	 */
	protected function isFulltextRoot(NodeData $nodeData) {
		if ($nodeData->getNodeType()->hasElasticSearch()) {
			$elasticSearchSettingsForNode = $nodeData->getNodeType()->getElasticSearch();
			if (isset($elasticSearchSettingsForNode['fulltext']['isRoot']) && $elasticSearchSettingsForNode['fulltext']['isRoot'] === TRUE) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * schedule node removal into the current bulk request.
	 *
	 * @param NodeData $nodeData
	 * @return string
	 */
	public function removeNode(NodeData $nodeData) {
		// TODO: handle deletion from the fulltext index as well
		$persistenceObjectIdentifier = $this->persistenceManager->getIdentifierByObject($nodeData);

		$this->currentBulkRequest[] = array(
			array(
				'delete' => array(
					'_type' => NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($nodeData->getNodeType()),
					'_id' => $persistenceObjectIdentifier
				)
			)
		);

		$this->logger->log(sprintf('NodeIndexer: Removed node %s from index (node actually removed). Persistence ID: %s', $nodeData->getContextPath(), $persistenceObjectIdentifier), LOG_DEBUG, NULL, 'ElasticSearch (CR)');
	}

	/**
	 * perform the current bulk request
	 *
	 * @return void
	 */
	public function flush() {
		if (count($this->currentBulkRequest) === 0) {
			return;
		}

		$content = '';
		foreach ($this->currentBulkRequest as $bulkRequestTuple) {
			$tupleAsJson = '';
			foreach ($bulkRequestTuple as $bulkRequestItem) {
				$itemAsJson = json_encode($bulkRequestItem);
				if ($itemAsJson === FALSE) {
					$this->logger->log('Indexing Error: Bulk request item could not be encoded as JSON - ' . json_last_error_msg(), LOG_ERR, $bulkRequestItem);
					continue 2;
				}
				$tupleAsJson .= $itemAsJson . chr(10);
			}
			$content .= $tupleAsJson;
		}

		if ($content !== '') {
			$responseAsLines = $this->getIndex()->request('POST', '/_bulk', array(), $content)->getOriginalResponse()->getContent();
			foreach (explode('\n', $responseAsLines) as $responseLine) {
				if (strpos($responseLine, 'error') !== FALSE) {
					$this->logger->log('Indexing Error: ' . $responseLine, LOG_ERR);
				}
			}
		}

		$this->currentBulkRequest = array();
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

	/**
	 * Update the index alias
	 *
	 * @return void
	 * @throws \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception
	 */
	public function updateIndexAlias() {
		$aliasName = $this->searchClient->getIndexName(); // The alias name is the unprefixed index name
		if ($this->getIndexName() === $aliasName) {
			throw new \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception('UpdateIndexAlias is only allowed to be called when $this->setIndexNamePostfix has been created.', 1383649061);
		}

		if (!$this->getIndex()->exists()) {
			throw new \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception('The target index for updateIndexAlias does not exist. This shall never happen.', 1383649125);
		}

		$aliasActions = array();
		try {
			$response = $this->searchClient->request('GET', '/*/_alias/' . $aliasName);
			if ($response->getStatusCode() !== 200) {
				throw new \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception('The alias "' . $aliasName . '" was not found with some unexpected error... (return code: ' . $response->getStatusCode(), 1383650137);
			}

			$indexNames = array_keys($response->getTreatedContent());

			foreach ($indexNames as $indexName) {
				$aliasActions[] = array(
					'remove' => array(
						'index' => $indexName,
						'alias' => $aliasName
					)
				);
			}
		} catch (\Flowpack\ElasticSearch\Transfer\Exception\ApiException $exception) {
			// in case of 404, do not throw an error...
		}

		$aliasActions[] = array(
			'add' => array(
				'index' => $this->getIndexName(),
				'alias' => $aliasName
			)
		);

		$this->searchClient->request('POST', '/_aliases', array(), \json_encode(array('actions' => $aliasActions)));
	}

	/**
	 * Remove old indices which are not active anymore (remember, each bulk index creates a new index from scratch,
	 * making the "old" index a stale one).
	 *
	 * @return array<string> a list of index names which were removed
	 */
	public function removeOldIndices() {
		$aliasName = $this->searchClient->getIndexName(); // The alias name is the unprefixed index name

		$currentlyLiveIndices = array_keys($this->searchClient->request('GET', '/*/_alias/' . $aliasName)->getTreatedContent());

		$indexStatus = $this->searchClient->request('GET', '/_status')->getTreatedContent();
		$allIndices = array_keys($indexStatus['indices']);

		$indicesToBeRemoved = array();

		foreach ($allIndices as $indexName) {
			if (strpos($indexName, $aliasName . '-') !== 0) {
				// filter out all indices not starting with the alias-name, as they are unrelated to our application
				continue;
			}

			if (array_search($indexName, $currentlyLiveIndices) !== FALSE) {
				// skip the currently live index names from deletion
				continue;
			}

			$indicesToBeRemoved[] = $indexName;
		}

		if (count($indicesToBeRemoved) > 0) {
			$this->searchClient->request('DELETE', '/' . implode(',', $indicesToBeRemoved) . '/');
		}

		return $indicesToBeRemoved;
	}
}