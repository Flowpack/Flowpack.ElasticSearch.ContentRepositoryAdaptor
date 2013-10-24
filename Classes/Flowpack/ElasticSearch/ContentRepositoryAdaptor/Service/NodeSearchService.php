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
	 * Finds the most recent nodes matching the given criteria.
	 *
	 *
	 * @param string $nodePath Parent node path where search starts. All nodes below that path are considered.
	 * @param string $sortingFieldName Name of the node property which is going to be used as a sorting criteria. Its value can be string, numeric or a date
	 * @param integer $maximumResults The number of maximum results. If "1" is specified, this function will still return an array, but with 1 element.
	 * @param integer $fromResult For pagination: the result number to start with. Index starts a 0.
	 * @param string $nodeTypeFilter (currently) a single node type name to filter the results
	 * @param \TYPO3\TYPO3CR\Domain\Service\ContextInterface $contentContext The content context, for example derived from the "current node"
	 * @return \TYPO3\TYPO3CR\Domain\Model Node
	 */
	public function findRecent($nodePath, $sortingFieldName, $maximumResults = 100, $fromResult = NULL, $nodeTypeFilter = NULL, ContextInterface $contentContext) {
		$searchQuery = array(
			'query' => array(
				'prefix' => array(
					'parentPath' => $nodePath
				)
			),
			'sort' => array(
				array('properties.' . $sortingFieldName => 'desc')
			),
			'size' => $maximumResults,
			'fields' => array('path')
		);

		if ($nodeTypeFilter !== NULL) {
			$searchQuery['filter'] = array(
				'and' => array(
					array('terms' => array('workspace' => array('live', $contentContext->getWorkspace()->getName()))),
					array('type' => array('value' => NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($nodeTypeFilter)))
				)
			);
		} else {
			$searchQuery['filter'] = array(
				'terms' => array('workspace' => array('live', $contentContext->getWorkspace()->getName()))
			);

		}

		if ($fromResult !== NULL) {
			$searchQuery['from'] = $fromResult;
		}

		$response = $this->index->request('GET', '/_search', array(), json_encode($searchQuery));
		$hits = $response->getTreatedContent()['hits'];

		if ($hits['total'] === 0) {
			return NULL;
		}

		$nodes = array();
		foreach ($hits['hits'] as $hit) {
			$nodes[] = $contentContext->getNode($hit['fields']['path']);
		}

		return $nodes;
	}
}