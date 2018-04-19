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
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\NodeTypeMappingBuilderInterface;
use Flowpack\ElasticSearch\Domain\Model\Index;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\Flow\Annotations as Flow;

/**
 * Document driver for Elasticsearch version 1.x
 *
 * @Flow\Scope("singleton")
 */
class DocumentDriver extends AbstractDriver implements DocumentDriverInterface
{
    /**
     * @Flow\Inject
     * @var NodeTypeMappingBuilderInterface
     */
    protected $nodeTypeMappingBuilder;

    /**
     * {@inheritdoc}
     */
    public function delete(NodeInterface $node, $identifier)
    {
        return [
            [
                'delete' => [
                    '_type' => $this->nodeTypeMappingBuilder->convertNodeTypeNameToMappingName($node->getNodeType()),
                    '_id' => $identifier
                ]
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDuplicateDocumentNotMatchingType(Index $index, $documentIdentifier, NodeType $nodeType)
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
                            '_type' => $this->nodeTypeMappingBuilder->convertNodeTypeNameToMappingName($nodeType->getName())
                        ]
                    ],
                ]
            ]
        ]));
    }
}
