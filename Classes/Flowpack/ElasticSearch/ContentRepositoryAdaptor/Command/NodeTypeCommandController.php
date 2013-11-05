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

/**
 * Provides CLI features for debugging the node types.
 *
 * TODO: move to TYPO3CR or Neos
 *
 * @Flow\Scope("singleton")
 */
class NodeTypeCommandController extends CommandController {

	/**
	 * @Flow\Inject
	 * @var \TYPO3\TYPO3CR\Domain\Service\NodeTypeManager
	 */
	protected $nodeTypeManager;

	/**
	 * @var \TYPO3\Flow\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * show mapping
	 *
	 * @param string $nodeType the node type to optionally filter for
	 */
	public function showCommand($nodeType = NULL) {
		$configuration = $this->nodeTypeManager->getFullConfiguration();

		if ($nodeType !== NULL) {
			$configuration = $configuration[$nodeType];
		}
		$this->output(\Symfony\Component\Yaml\Yaml::dump($configuration, 5, 2));
	}
}