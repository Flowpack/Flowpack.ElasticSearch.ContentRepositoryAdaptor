<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Command;

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
use TYPO3\Flow\Cli\CommandController;
use Flowpack\ElasticSearch\Domain\Factory\ClientFactory;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Mapping\NodeTypeMappingBuilder;

/**
 * Provides CLI features for index handling
 *
 * @Flow\Scope("singleton")
 */
class NodeIndexCommandController extends CommandController {

	/**
	 * @var string
	 */
	protected $indexName;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository
	 */
	protected $nodeDataRepository;

	/**
	 * @Flow\Inject
	 * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer
	 */
	protected $nodeIndexer;

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
	 * @var \TYPO3\Flow\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * @param array $settings
	 */
	public function injectSettings(array $settings) {
		$this->indexName = $settings['indexName'];
	}


	/**
	 * Show the mapping which would be sent to the ElasticSearch server
	 *
	 * @return void
	 */
	public function showMappingCommand() {
		$nodeTypeMappingCollection = $this->nodeTypeMappingBuilder->buildMappingInformation();
		foreach ($nodeTypeMappingCollection as $mapping) {
			/** @var Mapping $mapping */
			$this->output(\Symfony\Component\Yaml\Yaml::dump($mapping->asArray(), 5, 2));
			$this->outputLine();
		}
		$this->outputLine('------------');

		$mappingErrors = $this->nodeTypeMappingBuilder->getLastMappingErrors();
		if ($mappingErrors->hasErrors()) {
			$this->outputLine('<b>Mapping Errors</b>');
			foreach ($mappingErrors->getFlattenedErrors() as $errors) {
				foreach ($errors as $error) {
					$this->outputLine($error);
				}
			}
		}

		if ($mappingErrors->hasWarnings()) {
			$this->outputLine('<b>Mapping Warnings</b>');
			foreach ($mappingErrors->getFlattenedWarnings() as $warnings) {
				foreach ($warnings as $warning) {
					$this->outputLine($warning);
				}
			}
		}
	}

	/**
	 * (Re-)index all nodes
	 *
	 * This command indexes (or re-indexes) all nodes contained in the content repository.
	 *
	 * @param boolean $recreate if TRUE, completely removes the mapping, and reinitializes the index from scratch.
	 * @param integer $limit Amount of nodes to index at maximum
	 * @return void
	 */
	public function buildCommand($recreate = FALSE, $limit = NULL) {
		if ($recreate === TRUE) {
			$this->deleteCommand();
			$this->nodeTypeMappingBuilder->createIndexIfNotExists();
			$client = $this->clientFactory->create();

			$nodeTypeMappingCollection = $this->nodeTypeMappingBuilder->buildMappingInformation();
			foreach ($nodeTypeMappingCollection as $mapping) {
				/** @var Mapping $mapping */
				$mapping->getType()->getIndex()->setClient($client);
				$mapping->apply();
			}

			$this->logger->log('Updated Mapping.', LOG_INFO);
		}

		$this->logger->log(sprintf('Indexing %snodes ... ', ($limit !== NULL ? 'the first ' . $limit . ' ' : '')), LOG_INFO);

		$count = 0;
		foreach ($this->nodeDataRepository->findAll() as $nodeData) {
			if ($limit !== NULL && $count > $limit) {
				break;
			}
			$this->nodeIndexer->indexNode($nodeData);
			$this->logger->log(sprintf('  %s: %s', $nodeData->getWorkspace()->getName(), $nodeData->getPath()), LOG_DEBUG);
			$count ++;
		}

		$this->logger->log('Done.', LOG_INFO);
	}

	/**
	 * Deletes the node index
	 *
	 * This command deletes the whole node index.
	 *
	 * @return void
	 */
	public function deleteCommand() {
		$this->nodeIndexer->deleteIndex();
		$this->logger->log(sprintf('Deleted the node index "%s". ', $this->indexName), LOG_INFO);
	}
}