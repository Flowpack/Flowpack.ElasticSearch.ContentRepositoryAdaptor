<?php

declare(strict_types=1);

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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\NodeTypeMappingBuilderInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\ConfigurationException;
use Neos\Flow\Annotations as Flow;

/**
 * A factory for creating the NodeTypeMappingBuilder
 *
 * @Flow\Scope("singleton")
 */
class NodeTypeMappingBuilderFactory extends AbstractDriverSpecificObjectFactory
{
    /**
     * @return NodeTypeMappingBuilderInterface
     * @throws ConfigurationException
     */
    public function createNodeTypeMappingBuilder(): NodeTypeMappingBuilderInterface
    {
        return $this->resolve('nodeTypeMappingBuilder');
    }
}
