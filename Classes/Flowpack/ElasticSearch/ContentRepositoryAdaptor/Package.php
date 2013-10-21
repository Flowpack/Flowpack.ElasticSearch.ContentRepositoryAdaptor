<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor;

/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch.ContentRepositoryAdaptor". *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use TYPO3\Flow\Core\Booting\Step;
use TYPO3\Flow\Core\Bootstrap;
use \TYPO3\Flow\Package\Package as BasePackage;

/**
 * The ElasticSearch Package
 */
class Package extends BasePackage {

	/**
	 * Invokes custom PHP code directly after the package manager has been initialized.
	 *
	 * @param Bootstrap $bootstrap The current bootstrap
	 *
	 * @return void
	 */
	public function boot(Bootstrap $bootstrap) {
		$dispatcher = $bootstrap->getSignalSlotDispatcher();
		$package = $this;
		$dispatcher->connect('TYPO3\Flow\Core\Booting\Sequence', 'afterInvokeStep', function(Step $step) use ($package, $bootstrap) {
			if ($step->getIdentifier() === 'typo3.flow:persistence') {
				$package->registerIndexingSlots($bootstrap);
			}
		});
	}

	/**
	 * Registers slots for repository signals in order to be able to index nodes
	 *
	 * @param Bootstrap $bootstrap
	 */
	public function registerIndexingSlots(Bootstrap $bootstrap) {
		$this->configurationManager = $bootstrap->getObjectManager()->get('TYPO3\Flow\Configuration\ConfigurationManager');

		$bootstrap->getSignalSlotDispatcher()->connect('TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository', 'nodeAdded', 'Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer', 'indexNode');
		$bootstrap->getSignalSlotDispatcher()->connect('TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository', 'nodeUpdated', 'Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer', 'indexNode');
		$bootstrap->getSignalSlotDispatcher()->connect('TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository', 'nodeRemoved', 'Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer', 'removeNode');
	}
}

?>