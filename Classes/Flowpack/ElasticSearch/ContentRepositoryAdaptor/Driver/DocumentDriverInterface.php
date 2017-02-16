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
use TYPO3\TYPO3CR\Domain\Model\NodeType;

/**
 * Elasticsearch Document Driver Interface
 */
interface DocumentDriverInterface
{
    /**
     * Generate the query to delete Elastic document for the give node
     *
     * @param NodeInterface $node
     * @param $identifier
     * @return array
     */
    public function delete(NodeInterface $node, $identifier);

    /**
     * Generate the query to delete Elastic Document by Document Identifier but skip Document with the same Node Type
     *
     * @param Index $index
     * @param string $documentIdentifier
     * @param NodeType $nodeType
     * @return array
     */
    public function deleteDuplicateDocumentNotMatchingType(Index $index, $documentIdentifier, NodeType $nodeType);
}
