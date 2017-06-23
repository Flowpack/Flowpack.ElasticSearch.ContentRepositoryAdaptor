<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version2\Mapping;

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
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version1;
use TYPO3\Flow\Annotations as Flow;

/**
 * NodeTypeMappingBuilder for Elasticsearch version 2.x
 *
 * @Flow\Scope("singleton")
 */
class NodeTypeMappingBuilder extends Version1\Mapping\NodeTypeMappingBuilder
{
    /**
     * Store scripts used during indexing in Elasticsearch.
     *
     * @param Index $index
     * @return void
     */
    protected function setupStoredScripts(Index $index)
    {
        $this->client->request(
            'POST',
            '/_scripts/groovy/updateFulltextParts',
            [],
            json_encode(['script' => '
                fulltext = (ctx._source.containsKey("__fulltext") ? ctx._source.__fulltext : new HashMap());
                fulltextParts = (ctx._source.containsKey("__fulltextParts") ? ctx._source.__fulltextParts : new HashMap());
                
                ctx._source = newData;
                ctx._source.__fulltext = fulltext;
                ctx._source.__fulltextParts = fulltextParts'
            ])
        );

        $this->client->request(
            'POST',
            '/_scripts/groovy/regenerateFulltext',
            [],
            json_encode(['script' => '
                ctx._source.__fulltext = new HashMap();

                if (!(ctx._source.containsKey("__fulltextParts") && ctx._source.__fulltextParts instanceof Map)) {
                    ctx._source.__fulltextParts = new HashMap();
                }

                if (nodeIsRemoved || nodeIsHidden || fulltext.size() == 0) {
                    if (ctx._source.__fulltextParts.containsKey(identifier)) {
                        ctx._source.__fulltextParts.remove(identifier);
                    }
                } else {
                    ctx._source.__fulltextParts.put(identifier, fulltext);
                }

                ctx._source.__fulltextParts.each {
                    originNodeIdentifier, partContent -> partContent.each {
                        bucketKey, content ->
                            if (ctx._source.__fulltext.containsKey(bucketKey)) {
                                value = ctx._source.__fulltext[bucketKey] + " " + content.trim();
                            } else {
                                value = content.trim();
                            }
                            ctx._source.__fulltext[bucketKey] = value;
                    }
                }'
            ])
        );
    }
}
