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

use Flowpack\ElasticSearch\Domain\Model\Index;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Flowpack\ElasticSearch\Mapping\MappingCollection;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Configuration\ConfigurationManager;
use TYPO3\TYPO3CR\Domain\Model\NodeType;
use TYPO3\TYPO3CR\Domain\Service\NodeTypeManager;

/**
 * Builds the mapping information for TYPO3CR Node Types in Elastic Search
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
     * @var \TYPO3\Flow\Error\Result
     */
    protected $lastMappingErrors;

    /**
     * @Flow\Inject
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * Called by the Flow object framework after creating the object and resolving all dependencies.
     *
     * @param integer $cause Creation cause
     */
    public function initializeObject($cause)
    {
        if ($cause === \TYPO3\Flow\Object\ObjectManagerInterface::INITIALIZATIONCAUSE_CREATED) {
            $settings = $this->configurationManager->getConfiguration(\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.TYPO3CR.Search');
            $this->defaultConfigurationPerType = $settings['defaultConfigurationPerType'];
        }
    }

    /**
     * Converts a TYPO3CR Node Type name into a name which can be used for an Elastic Search Mapping
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
        $this->lastMappingErrors = new \TYPO3\Flow\Error\Result();

        $mappings = new MappingCollection(MappingCollection::TYPE_ENTITY);

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
                    $this->lastMappingErrors->addWarning(new \TYPO3\Flow\Error\Warning('Node Type "' . $nodeTypeName . '" - property "' . $propertyName . '": No ElasticSearch Mapping found.'));
                }
            }

            $mappings->add($mapping);
        }

        return $mappings;
    }

    /**
     * @return \TYPO3\Flow\Error\Result
     */
    public function getLastMappingErrors()
    {
        return $this->lastMappingErrors;
    }
}
