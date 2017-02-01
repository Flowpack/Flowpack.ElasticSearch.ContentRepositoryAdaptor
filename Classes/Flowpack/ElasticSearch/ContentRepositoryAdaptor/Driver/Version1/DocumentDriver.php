<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version1;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\AbstractDriver;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\DocumentDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\DriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Mapping\NodeTypeMappingBuilder;
use Flowpack\ElasticSearch\Domain\Model\Index;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

/**
 * Fulltext Indexer Driver for Elastic version 1.x
 *
 * @Flow\Scope("singleton")
 */
class DocumentDriver extends AbstractDriver implements DocumentDriverInterface
{
    /**
     * {@inheritdoc}
     */
    public function delete(NodeInterface $node, $identifier)
    {
        return [
            [
                'delete' => [
                    '_type' => NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($node->getNodeType()),
                    '_id' => $identifier
                ]
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function deleteByDocumentIdentifier(Index $index, NodeInterface $node, $documentIdentifier)
    {
        $index->request('DELETE', '/_query', [], json_encode([
            'query' => [
                'bool' => [
                    'must' => [
                        'ids' => [
                            'values' => [$documentIdentifier]
                        ]
                    ],
                    'must_not' => [
                        'term' => [
                            '_type' => NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($node->getNodeType()->getName())
                        ]
                    ],
                ]
            ]
        ]));
    }
}
