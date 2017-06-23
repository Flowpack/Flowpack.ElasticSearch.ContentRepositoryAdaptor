<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version1\Mapping;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\AbstractNodeTypeMappingBuilder;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Flowpack\ElasticSearch\Domain\Model\Index;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Flowpack\ElasticSearch\Mapping\MappingCollection;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Error\Result;
use TYPO3\Flow\Error\Warning;
use TYPO3\TYPO3CR\Domain\Model\NodeType;

/**
 * NodeTypeMappingBuilder for Elasticsearch version 1.x
 *
 * @Flow\Scope("singleton")
 */
class NodeTypeMappingBuilder extends AbstractNodeTypeMappingBuilder
{
    /**
     * @Flow\Inject
     * @var ElasticSearchClient
     */
    protected $client;

    /**
     * Builds a Mapping Collection from the configured node types
     *
     * @param Index $index
     * @return MappingCollection<\Flowpack\ElasticSearch\Domain\Model\Mapping>
     */
    public function buildMappingInformation(Index $index)
    {
        $this->lastMappingErrors = new Result();

        $mappings = new MappingCollection(MappingCollection::TYPE_ENTITY);

        $this->setupStoredScripts($index);

        /** @var NodeType $nodeType */
        foreach ($this->nodeTypeManager->getNodeTypes() as $nodeTypeName => $nodeType) {
            if ($nodeTypeName === 'unstructured' || $nodeType->isAbstract()) {
                continue;
            }

            $type = $index->findType(self::convertNodeTypeNameToMappingName($nodeTypeName));
            $mapping = new Mapping($type);
            $fullConfiguration = $nodeType->getFullConfiguration();
            if (isset($fullConfiguration['search']['elasticSearchMapping'])) {
                $mapping->setFullMapping($fullConfiguration['search']['elasticSearchMapping']);
            }

            // https://www.elastic.co/guide/en/elasticsearch/reference/2.4/dynamic-templates.html
            // 'not_analyzed' is necessary
            $mapping->addDynamicTemplate('dimensions', [
                'path_match' => '__dimensionCombinations.*',
                'match_mapping_type' => 'string',
                'mapping' => [
                    'type' => 'string',
                    'index' => 'not_analyzed'
                ]
            ]);
            $mapping->setPropertyByPath('__dimensionCombinationHash', [
                'type' => 'string',
                'index' => 'not_analyzed'
            ]);

            foreach ($nodeType->getProperties() as $propertyName => $propertyConfiguration) {
                if (isset($propertyConfiguration['search']) && isset($propertyConfiguration['search']['elasticSearchMapping'])) {
                    if (is_array($propertyConfiguration['search']['elasticSearchMapping'])) {
                        $mapping->setPropertyByPath($propertyName, $propertyConfiguration['search']['elasticSearchMapping']);
                    }
                } elseif (isset($propertyConfiguration['type']) && isset($this->defaultConfigurationPerType[$propertyConfiguration['type']]['elasticSearchMapping'])) {
                    if (is_array($this->defaultConfigurationPerType[$propertyConfiguration['type']]['elasticSearchMapping'])) {
                        $mapping->setPropertyByPath($propertyName, $this->defaultConfigurationPerType[$propertyConfiguration['type']]['elasticSearchMapping']);
                    }
                } else {
                    $this->lastMappingErrors->addWarning(new Warning('Node Type "' . $nodeTypeName . '" - property "' . $propertyName . '": No ElasticSearch Mapping found.'));
                }
            }

            $mappings->add($mapping);
        }

        return $mappings;
    }

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
