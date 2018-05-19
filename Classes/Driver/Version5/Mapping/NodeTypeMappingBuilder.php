<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version5\Mapping;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version2;
use Flowpack\ElasticSearch\Domain\Model\Index;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Flowpack\ElasticSearch\Mapping\MappingCollection;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\Error\Messages\Result;
use Neos\Error\Messages\Warning;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;

/**
 * NodeTypeMappingBuilder for Elasticsearch version 5.x
 *
 * @Flow\Scope("singleton")
 */
class NodeTypeMappingBuilder extends Version2\Mapping\NodeTypeMappingBuilder
{
    /**
     * Called by the Flow object framework after creating the object and resolving all dependencies.
     *
     * @param integer $cause Creation cause
     */
    public function initializeObject($cause)
    {
        parent::initializeObject($cause);
        if ($cause === ObjectManagerInterface::INITIALIZATIONCAUSE_CREATED) {
            $this->migrateConfigurationForElasticVersion5($this->defaultConfigurationPerType);
        }
    }

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

        /** @var NodeType $nodeType */
        foreach ($this->nodeTypeManager->getNodeTypes() as $nodeTypeName => $nodeType) {
            if ($nodeTypeName === 'unstructured' || $nodeType->isAbstract()) {
                continue;
            }

            $type = $index->findType($this->convertNodeTypeNameToMappingName($nodeTypeName));
            $mapping = new Mapping($type);
            $fullConfiguration = $nodeType->getFullConfiguration();
            if (isset($fullConfiguration['search']['elasticSearchMapping'])) {
                $fullMapping = $fullConfiguration['search']['elasticSearchMapping'];
                $this->migrateConfigurationForElasticVersion5($fullMapping);
                $mapping->setFullMapping($fullMapping);
            }

            // https://www.elastic.co/guide/en/elasticsearch/reference/5.4/dynamic-templates.html
            $mapping->addDynamicTemplate('dimensions', [
                'path_match' => '__dimensionCombinations.*',
                'match_mapping_type' => 'string',
                'mapping' => [
                    'type' => 'text'
                ]
            ]);
            $mapping->setPropertyByPath('__dimensionCombinationHash', [
                'type' => 'keyword'
            ]);

            foreach ($nodeType->getProperties() as $propertyName => $propertyConfiguration) {
                if (isset($propertyConfiguration['search']) && isset($propertyConfiguration['search']['elasticSearchMapping'])) {
                    if (is_array($propertyConfiguration['search']['elasticSearchMapping'])) {
                        $propertyMapping = $propertyConfiguration['search']['elasticSearchMapping'];
                        $this->migrateConfigurationForElasticVersion5($propertyMapping);
                        $mapping->setPropertyByPath($propertyName, $propertyMapping);
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
     * @param array $mapping
     * @return void
     */
    protected function migrateConfigurationForElasticVersion5(array &$mapping)
    {
        $this->migrateIncludeInAllToCopyTo($mapping);
        $this->adjustStringTypeMapping($mapping);
    }

    /**
     * include_in_all is deprecated with elasticsearch 5.x and raises
     * warnings on index creation
     *
     * @param array $mapping
     * @return void
     */
    protected function migrateIncludeInAllToCopyTo(array &$mapping)
    {
        $migrateIncludeInAll = function (&$mapping) {
            if (isset($mapping['include_in_all'])) {
                if ((bool)$mapping['include_in_all'] === true) {
                    $mapping['copy_to'] = '_all';
                }
                unset($mapping['include_in_all']);
            }
        };

        $migrateIncludeInAll($mapping);
        
        foreach ($mapping as &$item) {
            if (is_array($item)) {
                $migrateIncludeInAll($mapping);
                $this->migrateIncludeInAllToCopyTo($item);
            }
        }
    }

    /**
     * Adjust the mapping for string to text or keyword as needed.
     *
     * This is used to ease moving from ES 1.x and 2.x to 5.x by migrating the
     * mapping like this:
     *
     * | 2.x                                       | 5.x                              |
     * |-------------------------------------------|----------------------------------|
     * | "type": "string", "index": "no"           | "type": "text", "index": false   |
     * | "type": "string", "index": "analyzed"     | "type": "text", "index": true    |
     * | "type": "string", "index": "not_analyzed" | "type": "keyword", "index": true |
     *
     * @param array &$mapping
     * @return void
     */
    protected function adjustStringTypeMapping(array &$mapping)
    {
        $adjustStringTypeMapping = function (&$item) {
            if (isset($item['type']) && $item['type'] === 'string') {
                if (isset($item['index']) && $item['index'] === 'not_analyzed') {
                    $item['type'] = 'keyword';
                    $item['index'] = true;
                    unset($item['analyzer']);
                } elseif (isset($item['index']) && $item['index'] === 'no') {
                    $item['type'] = 'text';
                    $item['index'] = false;
                } elseif (isset($item['index']) && $item['index'] === 'analyzed') {
                    $item['type'] = 'text';
                    $item['index'] = true;
                } else {
                    $item['type'] = 'keyword';
                    $item['index'] = true;
                }
            }
        };

        $adjustStringTypeMapping($mapping);

        foreach ($mapping as &$item) {
            if (is_array($item)) {
                $adjustStringTypeMapping($mapping);
                $this->adjustStringTypeMapping($item);
            }
        }
    }
}
