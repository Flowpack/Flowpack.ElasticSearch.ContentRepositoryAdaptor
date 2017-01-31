<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\LoggerInterface;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\Flow\Annotations as Flow;

/**
 * Abstract Fulltext Indexer
 */
abstract class AbstractDriver
{
	/**
	 * @Flow\Inject
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * Whether the node is configured as fulltext root.
	 *
	 * @param NodeInterface $node
	 * @return boolean
	 */
	protected function isFulltextRoot(NodeInterface $node)
	{
		if ($node->getNodeType()->hasConfiguration('search')) {
			$elasticSearchSettingsForNode = $node->getNodeType()->getConfiguration('search');
			if (isset($elasticSearchSettingsForNode['fulltext']['isRoot']) && $elasticSearchSettingsForNode['fulltext']['isRoot'] === true) {
				return true;
			}
		}

		return false;
	}
}
