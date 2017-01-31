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
use Flowpack\ElasticSearch\Domain\Model\Index;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use Flowpack\ElasticSearch\Domain\Model\Document as ElasticSearchDocument;

/**
 * Driver Interface
 */
interface DriverInterface
{
	public function deleteIndices(array $indices);

	public function bulk(Index $index, $request);

	public function status();

	public function delete(NodeInterface $node, $identifier);

	public function currentlyLiveIndices($aliasName);

	public function deleteByContextPathHash(Index $index, NodeInterface $node, $contextPathHash);

	public function indexNames($aliasName);

	public function removeAlias($aliasName);

	public function aliasActions(array $actions);

	public function fulltextRootNode(NodeInterface $node, ElasticSearchDocument $document, array $documentData);

	public function fulltext(NodeInterface $node, array $fulltextIndexOfNode, $targetWorkspaceName = null);
}
