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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Mapping\NodeTypeMappingBuilder;
use Flowpack\ElasticSearch\Domain\Factory\ClientFactory;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cli\CommandController;

/**
 * Provides CLI features for handling of the Elastic Search mapping
 *
 * @Flow\Scope("singleton")
 */
class NodeMappingCommandController extends CommandController {

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
	 * Show the mapping which would be sent to the ElasticSearch server
	 *
	 * @return void
	 */
	public function showCommand() {
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
	 * Create mapping
	 *
	 * This command collects information about the currently available TYPO3CR node types and updates the mapping
	 * schema in Elastic Search accordingly.
	 *
	 * <b>NOTE</b>: This command will most likely be moved to the general Elastic Search package in a future version
	 * of the Content Repository Adaptor.
	 *
	 * @param string $client The client name for the configuration. Defaults to the default client configured.
	 *
	 * @return void
	 */
	public function createCommand($client = NULL) {
		$client = $this->clientFactory->create($client);

		$nodeTypeMappingCollection = $this->nodeTypeMappingBuilder->buildMappingInformation();
		foreach ($nodeTypeMappingCollection as $mapping) {
			/** @var Mapping $mapping */
			$mapping->getType()->getIndex()->setClient($client);
			$response = $mapping->apply();
			$treatedResponse = $response->getTreatedContent();
			if ($response->getStatusCode() === 200 && isset($treatedResponse['ok']) && $treatedResponse['ok'] === TRUE) {
				$this->logger->log(sprintf('%s:', $mapping->getType()->getName()), LOG_INFO);
				foreach ($mapping->getProperties() as $propertyName => $propertyInfo) {
					if (isset($propertyInfo['properties'])) {
						$this->logger->log(sprintf('  %s:', $propertyName), LOG_INFO);
						foreach ($propertyInfo['properties'] as $inlinePropertyName => $inlinePropertyInfo) {
							$this->logger->log(sprintf('    %s: %s', $inlinePropertyName, $inlinePropertyInfo['type']), LOG_INFO);
						}
					} else {
						$this->logger->log(sprintf('  %s: %s', $propertyName, $propertyInfo['type']), LOG_INFO);
					}
				}
				$this->logger->log('', LOG_INFO);
			} else {
				$this->logger->log(sprintf('%s: FAILED, response: %s - %s', $mapping->getType()->getName(), $response->getStatusCode(), $response->getOriginalResponse()->getContent()), LOG_ERR);
			}
		}
	}
}