<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version2;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version1;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Mapping\NodeTypeMappingBuilder;
use Flowpack\ElasticSearch\Domain\Model\Document as ElasticSearchDocument;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

/**
 * Indexer driver for Elasticsearch version 2.x
 *
 * @Flow\Scope("singleton")
 */
class IndexerDriver extends Version1\IndexerDriver
{
    /**
     * {@inheritdoc}
     */
    public function document($indexName, NodeInterface $node, ElasticSearchDocument $document, array $documentData)
    {
        if ($this->isFulltextRoot($node)) {
            // for fulltext root documents, we need to preserve the "__fulltext" field. That's why we use the
            // "update" API instead of the "index" API, with a custom script internally; as we
            // shall not delete the "__fulltext" part of the document if it has any.
            return [
                [
                    'update' => [
                        '_type' => $document->getType()->getName(),
                        '_id' => $document->getId(),
                        '_index' => $indexName,
                        '_retry_on_conflict' => 3
                    ]
                ],
                // http://www.elasticsearch.org/guide/en/elasticsearch/reference/2.4/docs-update.html
                [
                    'script' => [
                        'inline' => '
                                fulltext = (ctx._source.containsKey("__fulltext") ? ctx._source.__fulltext : new HashMap());
                                fulltextParts = (ctx._source.containsKey("__fulltextParts") ? ctx._source.__fulltextParts : new HashMap());
                                ctx._source = newData;
                                ctx._source.__fulltext = fulltext;
                                ctx._source.__fulltextParts = fulltextParts
                            ',
                        'params' => [
                            'newData' => $documentData
                        ]
                    ],
                    'upsert' => $documentData,
                    'lang' => 'groovy'
                ]
            ];
        }

        // non-fulltext-root documents can be indexed as-they-are
        return [
            [
                'index' => [
                    '_type' => $document->getType()->getName(),
                    '_id' => $document->getId()
                ]
            ],
            $documentData
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function fulltext(NodeInterface $node, array $fulltextIndexOfNode, $targetWorkspaceName = null)
    {
        $closestFulltextNode = $node;
        while (!$this->isFulltextRoot($closestFulltextNode)) {
            $closestFulltextNode = $closestFulltextNode->getParent();
            if ($closestFulltextNode === null) {
                // root of hierarchy, no fulltext root found anymore, abort silently...
                $this->logger->log(sprintf('NodeIndexer: No fulltext root found for node %s (%)', $node->getPath(), $node->getIdentifier()), LOG_WARNING, null, 'ElasticSearch (CR)');

                return null;
            }
        }

        $closestFulltextNodeContextPath = $closestFulltextNode->getContextPath();
        if ($targetWorkspaceName !== null) {
            $closestFulltextNodeContextPath = str_replace($node->getContext()->getWorkspace()->getName(), $targetWorkspaceName, $closestFulltextNodeContextPath);
        }
        $closestFulltextNodeDocumentIdentifier = sha1($closestFulltextNodeContextPath);

        if ($closestFulltextNode->isRemoved()) {
            // fulltext root is removed, abort silently...
            $this->logger->log(sprintf('NodeIndexer (%s): Fulltext root found for %s (%s) not updated, it is removed', $closestFulltextNodeDocumentIdentifier, $node->getPath(), $node->getIdentifier()), LOG_DEBUG, null, 'ElasticSearch (CR)');

            return null;
        }

        $this->logger->log(sprintf('NodeIndexer (%s): Updated fulltext index for %s (%s)', $closestFulltextNodeDocumentIdentifier, $closestFulltextNodeContextPath, $closestFulltextNode->getIdentifier()), LOG_DEBUG, null, 'ElasticSearch (CR)');

        $upsertFulltextParts = [];
        if (!empty($fulltextIndexOfNode)) {
            $upsertFulltextParts[$node->getIdentifier()] = $fulltextIndexOfNode;
        }

        return [
            [
                'update' => [
                    '_type' => NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($closestFulltextNode->getNodeType()->getName()),
                    '_id' => $closestFulltextNodeDocumentIdentifier
                ]
            ],
            // http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/docs-update.html
            [
                // first, update the __fulltextParts, then re-generate the __fulltext from all __fulltextParts
                'script' => [
                    'inline' => '
                        ctx._source.__fulltext = new HashMap();
                        
                        if (!ctx._source.containsKey("__fulltextParts")) {
                            ctx._source.__fulltextParts = new HashMap();
                        }
                        
                        if (nodeIsRemoved || nodeIsHidden || fulltext.size() == 0) {
                            if (ctx._source.__fulltextParts.containsKey(identifier)) {
                                ctx._source.__fulltextParts.remove(identifier);
                            }
                        } else {
                            ctx._source.__fulltextParts.put(identifier, fulltext);
                        }
    
                        ctx._source.__fulltextParts.each { originNodeIdentifier, partContent -> partContent.each { bucketKey, content ->
                                if (ctx._source.__fulltext.containsKey(bucketKey)) {
                                    value = ctx._source.__fulltext[bucketKey] + " " + content.trim();
                                } else {
                                    value = content.trim();
                                }
                                ctx._source.__fulltext[bucketKey] = value;
                            }
                        }
                    ',
                    'params' => [
                        'identifier' => $node->getIdentifier(),
                        'nodeIsRemoved' => $node->isRemoved(),
                        'nodeIsHidden' => $node->isHidden(),
                        'fulltext' => $fulltextIndexOfNode
                    ],
                ],
                'upsert' => [
                    '__fulltext' => $fulltextIndexOfNode,
                    '__fulltextParts' => $upsertFulltextParts
                ],
                'lang' => 'groovy'
            ]
        ];
    }
}
