<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Mapping;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\NodeTypeIndexingConfiguration;
use Flowpack\ElasticSearch\Domain\Model\Index;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Flowpack\ElasticSearch\Mapping\MappingCollection;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;

/**
 * Builds the mapping information for ContentRepository Node Types in Elasticsearch
 *
 * @Flow\Scope("singleton")
 */
class NodeTypeMappingBuilder
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
     * @var \Neos\Error\Messages\Result
     */
    protected $lastMappingErrors;

    /**
     * @Flow\Inject
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var NodeTypeIndexingConfiguration
     * @Flow\Inject
     */
    protected $nodeTypeIndexingConfiguration;

    /**
     * Called by the Flow object framework after creating the object and resolving all dependencies.
     *
     * @param integer $cause Creation cause
     */
    public function initializeObject($cause)
    {
        if ($cause === \Neos\Flow\ObjectManagement\ObjectManagerInterface::INITIALIZATIONCAUSE_CREATED) {
            $settings = $this->configurationManager->getConfiguration(\Neos\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.ContentRepository.Search');
            $this->defaultConfigurationPerType = $settings['defaultConfigurationPerType'];
        }
    }

    /**
     * Converts a ContentRepository Node Type name into a name which can be used for an Elasticsearch Mapping
     *
     * @param string $nodeTypeName
     * @return string
     */
    public static function convertNodeTypeNameToMappingName($nodeTypeName)
    {
        return str_replace('.', '-', $nodeTypeName);
    }

    /**
     * Builds a Mapping Collection from the configured node types
     *
     * @param \Flowpack\ElasticSearch\Domain\Model\Index $index
     * @return \Flowpack\ElasticSearch\Mapping\MappingCollection<\Flowpack\ElasticSearch\Domain\Model\Mapping>
     */
    public function buildMappingInformation(Index $index)
    {
        $this->lastMappingErrors = new \Neos\Error\Messages\Result();

        $mappings = new MappingCollection(MappingCollection::TYPE_ENTITY);

        /** @var NodeType $nodeType */
        foreach ($this->nodeTypeManager->getNodeTypes() as $nodeTypeName => $nodeType) {
            if ($nodeTypeName === 'unstructured' || $nodeType->isAbstract()) {
                continue;
            }

            if ($this->nodeTypeIndexingConfiguration->isIndexable($nodeType) === false) {
                continue;
            }

            $type = $index->findType(self::convertNodeTypeNameToMappingName($nodeTypeName));
            $mapping = new Mapping($type);
            $fullConfiguration = $nodeType->getFullConfiguration();
            if (isset($fullConfiguration['search']['elasticSearchMapping'])) {
                $mapping->setFullMapping($fullConfiguration['search']['elasticSearchMapping']);
            }

            // http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/mapping-root-object-type.html#_dynamic_templates
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
                    $this->lastMappingErrors->addWarning(new \Neos\Error\Messages\Warning('Node Type "' . $nodeTypeName . '" - property "' . $propertyName . '": No ElasticSearch Mapping found.'));
                }
            }

            $mappings->add($mapping);
        }

        return $mappings;
    }

    /**
     * @return \Neos\Error\Messages\Result
     */
    public function getLastMappingErrors()
    {
        return $this->lastMappingErrors;
    }
}
