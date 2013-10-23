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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Mapping\NodeTypeMappingBuilder;
use Flowpack\ElasticSearch\Domain\Model\GenericType;
use Flowpack\ElasticSearch\Domain\Model\Index;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Neos\Domain\Service\ContentContext;
use TYPO3\TYPO3CR\Domain\Model\NodeData;
use Flowpack\ElasticSearch\Domain\Factory\ClientFactory;
use Flowpack\ElasticSearch\Domain\Model\Client;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Domain\Model\NodeType;
use Flowpack\ElasticSearch\Domain\Model\Document as ElasticSearchDocument;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\ElasticSearch;
use TYPO3\TYPO3CR\Domain\Service\ContextInterface;


/**
 * Search service for TYPO3CR nodes
 *
 * @Flow\Scope("singleton")
 */
class NodeSearchService {

	/**
	 * @var string
	 */
	protected $indexName;

	/**
	 * @var Index
	 */
	protected $index;

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
		$this->index = $this->searchClient->findIndex($this->indexName);
	}

	/**
	 *
	 *
	 * @param string $nodePath
	 * @param string $dateTimeFieldName
	 * @param integer $maximumResults
	 * @param integer $fromResult
	 * @param string $nodeTypeFilter
	 * @param \TYPO3\TYPO3CR\Domain\Service\ContextInterface $contentContext
	 * @return string
	 */
	public function findRecent($nodePath, $dateTimeFieldName, $maximumResults = 100, $fromResult = NULL, $nodeTypeFilter = NULL, ContextInterface $contentContext) {
		$searchQuery = array(
			'query' => array(
				'prefix' => array(
					'parentPath' => $nodePath
				)
			),
			'sort' => array(
				array('properties.' . $dateTimeFieldName => 'desc')
			),
			'size' => $maximumResults,
			'fields' => array('path', 'parentPath', 'properties')
		);

		if ($nodeTypeFilter !== NULL) {
			$searchQuery['filter'] = array(
				'type' => array(
					'value' => NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($nodeTypeFilter)
				)
			);
		}

		$this->systemLogger->log('Query', LOG_DEBUG, $searchQuery);

		$response = $this->index->request('GET', '/_search', array(), json_encode($searchQuery));
		$hits = $response->getTreatedContent()['hits'];

		$this->systemLogger->log('Path', LOG_DEBUG, $nodePath);
		$this->systemLogger->log('Response', LOG_DEBUG, $hits);
		if ($hits['total'] !== 1) {
			return NULL;
		}

		$this->systemLogger->log('Response', LOG_DEBUG, $hits);
	}
}