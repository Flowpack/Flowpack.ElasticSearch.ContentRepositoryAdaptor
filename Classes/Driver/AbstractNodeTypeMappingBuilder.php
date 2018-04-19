<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Error\Messages\Result;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;

/**
 * Builds the mapping information for Content Repository Node Types in Elasticsearch
 *
 * @Flow\Scope("singleton")
 */
abstract class AbstractNodeTypeMappingBuilder implements NodeTypeMappingBuilderInterface
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
     * @var Result
     */
    protected $lastMappingErrors;

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
     * Converts a Content Repository Node Type name into a name which can be used for an Elasticsearch Mapping
     *
     * @param string $nodeTypeName
     * @return string
     */
    public function convertNodeTypeNameToMappingName($nodeTypeName)
    {
        return str_replace('.', '-', $nodeTypeName);
    }

    /**
     * @return Result
     */
    public function getLastMappingErrors()
    {
        return $this->lastMappingErrors;
    }
}
