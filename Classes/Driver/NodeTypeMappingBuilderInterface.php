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

use Flowpack\ElasticSearch\Domain\Model\Index;
use Flowpack\ElasticSearch\Mapping\MappingCollection;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\Error\Messages\Result;

/**
 * NodeTypeMappingBuilder Interface
 */
interface NodeTypeMappingBuilderInterface
{
    /**
     * Builds a Mapping Collection from the configured node types
     *
     * @param Index $index
     * @return MappingCollection<\Flowpack\ElasticSearch\Domain\Model\Mapping>
     */
    public function buildMappingInformation(ContentRepositoryId $contentRepositoryId, Index $index): MappingCollection;

    /**
     * @return Result
     */
    public function getLastMappingErrors(): Result;
}
