<?php
declare(strict_types=1);

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

use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Error\Messages\Result;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;

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
     * @var Result
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
     * @throws InvalidConfigurationTypeException
     */
    public function initializeObject($cause): void
    {
        if ($cause === ObjectManagerInterface::INITIALIZATIONCAUSE_CREATED) {
            $settings = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.ContentRepository.Search');
            $this->defaultConfigurationPerType = $settings['defaultConfigurationPerType'];
        }
    }

    /**
     * @return Result
     */
    public function getLastMappingErrors(): Result
    {
        return $this->lastMappingErrors;
    }
}
