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
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Service\NodeTypeManager;
use TYPO3\TYPO3CR\Search\Indexer\AbstractNodeIndexer;

/**
 * Indexer for Content Repository Nodes. Triggered from the NodeIndexingManager.
 *
 * Internally, uses a bulk request.
 *
 * @Flow\Scope("singleton")
 */
class NodeIndexer extends AbstractNodeIndexer {

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
	 * The current ElasticSearch bulk request, in the format required by http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/docs-bulk.html
	 *
	 * @var array
	 */
	protected $currentBulkRequest = array();

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
	 * @param string $indexNamePostfix
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
		$index = $this->searchClient->findIndex($this->getIndexName());
		$index->setSettingsKey($this->searchClient->getIndexName());
		return $index;
	}

	/**
	 * index this node, and add it to the current bulk request.
	 *
	 * @param NodeInterface $node
	 * @param string $targetWorkspaceName In case this is triggered during publishing, a workspace name will be passed in
	 * @return void
	 * @throws \TYPO3\TYPO3CR\Search\Exception\IndexingException
	 */
	public function indexNode(NodeInterface $node, $targetWorkspaceName = NULL) {
		$contextPath = $node->getContextPath();

		if ($this->settings['indexAllWorkspaces'] === FALSE) {
			// we are only supposed to index the live workspace.
			// We need to check the workspace at two occasions; checking the
			// $targetWorkspaceName and the workspace name of the node's context as fallback
			if ($targetWorkspaceName !== NULL && $targetWorkspaceName !== 'live') {
				return;
			}

			if ($targetWorkspaceName === NULL && $node->getContext()->getWorkspaceName() !== 'live') {
				return;
			}
		}


		if ($targetWorkspaceName !== NULL) {
			$contextPath = str_replace($node->getContext()->getWorkspace()->getName(), $targetWorkspaceName, $contextPath);
		}

		$contextPathHash = sha1($contextPath);
		$nodeType = $node->getNodeType();

		$mappingType = $this->getIndex()->findType(NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($nodeType));

		// Remove document with the same contextPathHash but different NodeType, required after NodeType change
		$this->getIndex()->request('DELETE', '/_query', array(), json_encode([
			'query' => [
				'bool' => [
					'must' => [
						'ids' => [
							'values' => [ $contextPathHash ]
						]
					],
					'must_not' => [
						'term' => [
							'_type' => str_replace('.', '/', $node->getNodeType()->getName())
						]
					],
				]
			]
		]));

		if ($node->isRemoved()) {
			// TODO: handle deletion from the fulltext index as well
			$mappingType->deleteDocumentById($contextPathHash);
			$this->logger->log(sprintf('NodeIndexer: Removed node %s from index (node flagged as removed). ID: %s', $contextPath, $contextPathHash), LOG_DEBUG, NULL, 'ElasticSearch (CR)');

			return;
		}

		$logger = $this->logger;
		$fulltextIndexOfNode = array();
		$nodePropertiesToBeStoredInIndex = $this->extractPropertiesAndFulltext($node, $fulltextIndexOfNode, function($propertyName) use ($logger, $contextPathHash) {
			$logger->log(sprintf('NodeIndexer (%s) - Property "%s" not indexed because no configuration found.', $contextPathHash, $propertyName), LOG_DEBUG, NULL, 'ElasticSearch (CR)');
		});

		$document = new ElasticSearchDocument($mappingType,
			$nodePropertiesToBeStoredInIndex,
			$contextPathHash
		);

		$documentData = $document->getData();
		if ($targetWorkspaceName !== NULL) {
			$documentData['__workspace'] = $targetWorkspaceName;
		}

		$dimensionCombinations = $node->getContext()->getDimensions();
		if (is_array($dimensionCombinations)) {
			$documentData['__dimensionCombinations'] = $dimensionCombinations;
		}

		if ($this->isFulltextEnabled($node)) {
			if ($this->isFulltextRoot($node)) {
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
						'upsert' => $documentData,
						'lang' => 'groovy'


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

			$this->updateFulltext($node, $fulltextIndexOfNode, $targetWorkspaceName);
		}

		$this->logger->log(sprintf('NodeIndexer: Added / updated node %s. ID: %s', $contextPath, $contextPathHash), LOG_DEBUG, NULL, 'ElasticSearch (CR)');
	}

	/**
	 *
	 *
	 * @param NodeInterface $node
	 * @param array $fulltextIndexOfNode
	 * @param string $targetWorkspaceName
	 * @return void
	 */
	protected function updateFulltext(NodeInterface $node, array $fulltextIndexOfNode, $targetWorkspaceName = NULL) {
		if ((($targetWorkspaceName !== NULL && $targetWorkspaceName !== 'live') || $node->getWorkspace()->getName() !== 'live') || count($fulltextIndexOfNode) === 0) {
			return;
		}

		$closestFulltextNode = $node;
		while (!$this->isFulltextRoot($closestFulltextNode)) {
			$closestFulltextNode = $closestFulltextNode->getParent();
			if ($closestFulltextNode === NULL) {
				// root of hierarchy, no fulltext root found anymore, abort silently...
				$this->logger->log('No fulltext root found for ' . $node->getPath(), LOG_WARNING);
				return;
			}
		}

		$closestFulltextNodeContextPath = str_replace($closestFulltextNode->getContext()->getWorkspace()->getName(), 'live', $closestFulltextNode->getContextPath());
		$closestFulltextNodeContextPathHash = sha1($closestFulltextNodeContextPath);

		$this->currentBulkRequest[] = array(
			array(
				'update' => array(
					'_type' => NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($closestFulltextNode->getNodeType()->getName()),
					'_id' => $closestFulltextNodeContextPathHash
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

					Iterator<LinkedHashMap.Entry<String, LinkedHashMap>> fulltextByNode = ctx._source.__fulltextParts.entrySet().iterator();
					for (fulltextByNode; fulltextByNode.hasNext();) {
						Iterator<LinkedHashMap.Entry<String, String>> elementIterator = fulltextByNode.next().getValue().entrySet().iterator();
						for (elementIterator; elementIterator.hasNext();) {
							Map.Entry<String, String> element = elementIterator.next();
							String value;

							if (ctx._source.__fulltext.containsKey(element.key)) {
								value = ctx._source.__fulltext[element.key] + " " + element.value.trim();
							} else {
								value = element.value.trim();
							}

							ctx._source.__fulltext[element.key] = value;
						}
					}
				',
				'params' => array(
					'identifier' => $node->getIdentifier(),
					'fulltext' => $fulltextIndexOfNode
				),
				'upsert' => array(
					'__fulltext' => $fulltextIndexOfNode,
					'__fulltextParts' => array(
						$node->getIdentifier() => $fulltextIndexOfNode
					)
				),
				'lang' => 'groovy'
			)
		);
	}


	/**
	 * Whether the node is configured as fulltext root.
	 *
	 * @param NodeInterface $node
	 * @return boolean
	 */
	protected function isFulltextRoot(NodeInterface $node) {
		if ($node->getNodeType()->hasConfiguration('search')) {
			$elasticSearchSettingsForNode = $node->getNodeType()->getConfiguration('search');
			if (isset($elasticSearchSettingsForNode['fulltext']['isRoot']) && $elasticSearchSettingsForNode['fulltext']['isRoot'] === TRUE) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Schedule node removal into the current bulk request.
	 *
	 * @param NodeInterface $node
	 * @return string
	 */
	public function removeNode(NodeInterface $node) {
		if ($this->settings['indexAllWorkspaces'] === FALSE) {
			if ($node->getContext()->getWorkspaceName() !== 'live') {
				return;
			}
		}

		// TODO: handle deletion from the fulltext index as well
		$identifier = sha1($node->getContextPath());

		$this->currentBulkRequest[] = array(
			array(
				'delete' => array(
					'_type' => NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($node->getNodeType()),
					'_id' => $identifier
				)
			)
		);

		$this->logger->log(sprintf('NodeIndexer: Removed node %s from index (node actually removed). Persistence ID: %s', $node->getContextPath(), $identifier), LOG_DEBUG, NULL, 'ElasticSearch (CR)');
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
			foreach (explode("\n", $responseAsLines) as $responseLine) {
				$response = json_decode($responseLine);
				if (!is_object($response) || (isset($response->errors) && $response->errors !== FALSE)) {
					$this->logIndexingErrors($this->currentBulkRequest, $responseLine);
				}
			}
		}

		$this->currentBulkRequest = array();
	}

	/**
	 * @param string $bulkRequest
	 * @param string $errors
	 */
	protected function logIndexingErrors($bulkRequest, $errors) {
		if (!file_exists(FLOW_PATH_DATA . 'Logs/ElasticSearch')) {
			mkdir(FLOW_PATH_DATA . 'Logs/ElasticSearch');
		}
		if (file_exists(FLOW_PATH_DATA . 'Logs/ElasticSearch') && is_dir(FLOW_PATH_DATA . 'Logs/ElasticSearch') && is_writable(FLOW_PATH_DATA . 'Logs/ElasticSearch')) {
			$referenceCode = date('YmdHis', $_SERVER['REQUEST_TIME']) . substr(md5(rand()), 0, 6);
			$dumpPathAndFilename = FLOW_PATH_DATA . 'Logs/ElasticSearch/' . $referenceCode . '.txt';
			file_put_contents($dumpPathAndFilename, $this->renderIndexingErrors($bulkRequest, $errors));
			$this->logger->log(sprintf('Indexing errors detected - See also: Data/Logs/ElasticSearch/%s', basename($dumpPathAndFilename)), LOG_ERR, array(), 'Flowpack.ElasticSearch.ContentRepositoryAdaptor', __CLASS__, __FUNCTION__);
		} else {
			$this->logger->log(sprintf('Could not write indexing errors backtrace into %s because the directory could not be created or is not writable.', FLOW_PATH_DATA . 'Logs/ElasticSearch/'), LOG_WARNING, array(), 'Flowpack.ElasticSearch.ContentRepositoryAdaptor', __CLASS__, __FUNCTION__);
		}
	}

	/**
	 * @param array $bulkRequest
	 * @param string $errors
	 * @return string
	 */
	protected function renderIndexingErrors(array $bulkRequest, $errors) {
		$bulkRequest = json_encode($bulkRequest, JSON_PRETTY_PRINT);
		$errors = json_encode(json_decode($errors, TRUE), JSON_PRETTY_PRINT);
		return sprintf("Payload:\n========\n\n%s\n\nErrors:\n=======\n\n%s\n\n", $bulkRequest, $errors);
	}

	/**
	 * Update the index alias
	 *
	 * @return void
	 * @throws Exception
	 * @throws \Flowpack\ElasticSearch\Transfer\Exception\ApiException
	 * @throws \Exception
	 */
	public function updateIndexAlias() {
		$aliasName = $this->searchClient->getIndexName(); // The alias name is the unprefixed index name
		if ($this->getIndexName() === $aliasName) {
			throw new Exception('UpdateIndexAlias is only allowed to be called when $this->setIndexNamePostfix has been created.', 1383649061);
		}

		if (!$this->getIndex()->exists()) {
			throw new Exception('The target index for updateIndexAlias does not exist. This shall never happen.', 1383649125);
		}

		$aliasActions = array();
		try {
			$response = $this->searchClient->request('GET', '/*/_alias/' . $aliasName);
			if ($response->getStatusCode() !== 200) {
				throw new Exception('The alias "' . $aliasName . '" was not found with some unexpected error... (return code: ' . $response->getStatusCode() . ')', 1383650137);
			}

			$indexNames = array_keys($response->getTreatedContent());

			if ($indexNames === array()) {
				// if there is an actual index with the name we want to use as alias, remove it now
				$response = $this->searchClient->request('HEAD', '/' . $aliasName);
				if ($response->getStatusCode() === 200) {
					$response = $this->searchClient->request('DELETE', '/' . $aliasName);
					if ($response->getStatusCode() !== 200) {
						throw new Exception('The index "' . $aliasName . '" could not be removed to be replaced by an alias. (return code: ' . $response->getStatusCode() . ')', 1395419177);
					}
				}
			} else {
				foreach ($indexNames as $indexName) {
					$aliasActions[] = array(
						'remove' => array(
							'index' => $indexName,
							'alias' => $aliasName
						)
					);
				}
			}
		} catch (\Flowpack\ElasticSearch\Transfer\Exception\ApiException $exception) {
			// in case of 404, do not throw an error...
			if ($exception->getResponse()->getStatusCode() !== 404) {
				throw $exception;
			}
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