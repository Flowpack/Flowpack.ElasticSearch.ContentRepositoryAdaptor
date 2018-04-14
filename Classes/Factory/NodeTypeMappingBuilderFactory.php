<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Factory;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\DriverConfigurationException;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\NodeTypeMappingBuilderInterface;

/**
 * A factory for creating the NodeTypeMappingBuilder
 *
 * @Flow\Scope("singleton")
 */
class NodeTypeMappingBuilderFactory extends AbstractDriverSpecificObjectFactory
{
    /**
     * @return NodeTypeMappingBuilderInterface
     * @throws DriverConfigurationException
     */
    public function createNodeTypeMappingBuilder()
    {
        return $this->resolve('nodeTypeMappingBuilder');
    }
}
