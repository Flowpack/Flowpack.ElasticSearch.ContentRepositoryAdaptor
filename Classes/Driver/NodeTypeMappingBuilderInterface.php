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

use Flowpack\ElasticSearch\Domain\Model\Index;
use Flowpack\ElasticSearch\Mapping\MappingCollection;
use Neos\Flow\Error\Result;

/**
 * NodeTypeMappingBuilder Interface
 */
interface NodeTypeMappingBuilderInterface
{

    /**
     * Converts a Content Repository Node Type name into a name which can be used for an Elasticsearch Mapping
     *
     * @param string $nodeTypeName
     * @return string
     */
    public static function convertNodeTypeNameToMappingName($nodeTypeName);

    /**
     * Builds a Mapping Collection from the configured node types
     *
     * @param Index $index
     * @return MappingCollection<\Flowpack\ElasticSearch\Domain\Model\Mapping>
     */
    public function buildMappingInformation(Index $index);

    /**
     * @return Result
     */
    public function getLastMappingErrors();
}
