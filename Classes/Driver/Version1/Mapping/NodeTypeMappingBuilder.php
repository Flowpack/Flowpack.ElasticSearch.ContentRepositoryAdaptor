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
use Flowpack\ElasticSearch\Domain\Model\Index;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Flowpack\ElasticSearch\Mapping\MappingCollection;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Error\Messages\Result;
use Neos\Error\Messages\Warning;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;

/**
 * NodeTypeMappingBuilder for Elasticsearch version 1.x
 * Builds the mapping information for ContentRepository Node Types in Elasticsearch
 * @Flow\Scope("singleton")
 */
class NodeTypeMappingBuilder extends AbstractNodeTypeMappingBuilder
{
    /**
     * The default configuration for a given property type in NodeTypes.yaml, if no explicit elasticSearch section defined there.
     *
     * @var array
     */
    protected $defaultConfigurationPerType;

    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @Flow\Inject
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * Called by the Flow object framework after creating the object and resolving all dependencies.
     *
     * @param integer $cause Creation cause
     * @throws InvalidConfigurationTypeException
     */
    public function initializeObject($cause)
    {
        if ($cause === ObjectManagerInterface::INITIALIZATIONCAUSE_CREATED) {
            $settings = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.ContentRepository.Search');
            $this->defaultConfigurationPerType = $settings['defaultConfigurationPerType'];
        }
    }

    /**
     * Converts a ContentRepository Node Type name into a name which can be used for an Elasticsearch Mapping
     *
     * @param string $nodeTypeName
     * @return string
     */
    public function convertNodeTypeNameToMappingName($nodeTypeName)
    {
        return str_replace('.', '-', $nodeTypeName);
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
}
