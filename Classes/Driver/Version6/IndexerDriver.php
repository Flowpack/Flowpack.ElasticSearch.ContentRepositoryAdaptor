<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version6;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Domain\Model\TargetContextPath;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\AbstractIndexerDriver;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\IndexerDriverInterface;
use Flowpack\ElasticSearch\Domain\Model\Document as ElasticSearchDocument;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\Utility\LogEnvironment;

/**
 * Indexer driver for Elasticsearch version 6.x
 *
 * @Flow\Scope("singleton")
 */
class IndexerDriver extends AbstractIndexerDriver implements IndexerDriverInterface
{

    /**
     * {@inheritdoc}
     */
    public function document(string $indexName, NodeInterface $node, ElasticSearchDocument $document, array $documentData): array
    {
        if ($this->isFulltextRoot($node)) {
            // for fulltext root documents, we need to preserve the "__fulltext" field. That's why we use the
            // "update" API instead of the "index" API, with a custom script internally; as we
            // shall not delete the "__fulltext" part of the document if it has any.
            return [
                [
                    'update' => [
                        '_id' => $document->getId(),
                        '_index' => $indexName,
                        'retry_on_conflict' => 3
                    ]
                ],
                // http://www.elasticsearch.org/guide/en/elasticsearch/reference/5.0/docs-update.html
                [
                    'script' => [
                        'lang' => 'painless',
                        'source' => '
                            HashMap fulltext = (ctx._source.containsKey("__fulltext") && ctx._source.__fulltext instanceof Map ? ctx._source.__fulltext : new HashMap());
                            HashMap fulltextParts = (ctx._source.containsKey("__fulltextParts") && ctx._source.__fulltextParts instanceof Map ? ctx._source.__fulltextParts : new HashMap());
                            ctx._source = params.newData;
                            ctx._source.__fulltext = fulltext;
                            ctx._source.__fulltextParts = fulltextParts',
                        'params' => [
                            'newData' => $documentData
                        ]
                    ],
                    'upsert' => $documentData
                ]
            ];
        }

        // non-fulltext-root documents can be indexed as-they-are
        return [
            [
                'index' => [
                    '_id' => $document->getId(),
                    '_index' => $indexName,
                ]
            ],
            $documentData
        ];
    }

    /**
     * {@inheritdoc}
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     */
    public function fulltext(NodeInterface $node, array $fulltextIndexOfNode, string $targetWorkspaceName = null): array
    {
        $closestFulltextNode = $this->findClosestFulltextRoot($node);
        if ($closestFulltextNode === null) {
            return [];
        }

        $closestFulltextNodeContextPath = $closestFulltextNode->getContextPath();
        if ($targetWorkspaceName !== null) {
            $closestFulltextNodeContextPath = (string)(new TargetContextPath($node, $targetWorkspaceName, $closestFulltextNodeContextPath));
        }
        $closestFulltextNodeDocumentIdentifier = sha1($closestFulltextNodeContextPath);

        if ($closestFulltextNode->isRemoved()) {
            // fulltext root is removed, abort silently...
            $this->logger->debug(sprintf('NodeIndexer (%s): Fulltext root found for %s (%s) not updated, it is removed', $closestFulltextNodeDocumentIdentifier, $node->getPath(), $node->getIdentifier()), LogEnvironment::fromMethodName(__METHOD__));

            return [];
        }

        $upsertFulltextParts = [];
        if (!empty($fulltextIndexOfNode)) {
            $upsertFulltextParts[$node->getIdentifier()] = $fulltextIndexOfNode;
        }

        return [
            [
                'update' => [
                    '_id' => $closestFulltextNodeDocumentIdentifier
                ]
            ],
            // http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/docs-update.html
            [
                // first, update the __fulltextParts, then re-generate the __fulltext from all __fulltextParts
                'script' => [
                    'lang' => 'painless',
                    'source' => '
                        ctx._source.__fulltext = new HashMap();
                        if (!ctx._source.containsKey("__fulltextParts") || !(ctx._source.__fulltextParts instanceof Map)) {
                            ctx._source.__fulltextParts = new HashMap();
                        }

                        if (params.nodeIsRemoved || params.nodeIsHidden || params.fulltext.size() == 0) {
                            if (ctx._source.__fulltextParts.containsKey(params.identifier)) {
                                ctx._source.__fulltextParts.remove(params.identifier);
                            }
                        } else {
                            ctx._source.__fulltextParts.put(params.identifier, params.fulltext);
                        }

                        for (fulltextPart in ctx._source.__fulltextParts.entrySet()) {
                            for (entry in fulltextPart.getValue().entrySet()) {
                                def value = "";
                                if (ctx._source.__fulltext.containsKey(entry.getKey())) {
                                    value = ctx._source.__fulltext[entry.getKey()] + " " + entry.getValue().trim();
                                } else {
                                    value = entry.getValue().trim();
                                }
                                ctx._source.__fulltext[entry.getKey()] = value;
                            }
                        }',
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
                ]
            ]
        ];
    }
}
