<?php

declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Command;

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
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\DimensionsService;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Neos\ContentRepository\Domain\Service\ContentDimensionCombinator;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides CLI features for checking mapping informations
 *
 * @Flow\Scope("singleton")
 */
class NodeIndexMappingCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var NodeIndexer
     */
    protected $nodeIndexer;

    /**
     * @Flow\Inject
     * @var DimensionsService
     */
    protected $dimensionsService;

    /**
     * @var ContentDimensionCombinator
     * @Flow\Inject
     */
    protected $contentDimensionCombinator;

    /**
     * @Flow\Inject
     * @var NodeTypeMappingBuilderInterface
     */
    protected $nodeTypeMappingBuilder;

    /**
     * Mapping between dimensions presets and index name
     */
    public function indicesCommand(): void
    {
        $indexName = $this->nodeIndexer->getIndexName();
        foreach ($this->contentDimensionCombinator->getAllAllowedCombinations() as $dimensionValues) {
            $this->outputLine('<info>%s-%s</info> %s', [$indexName, $this->dimensionsService->hash($dimensionValues), \json_encode($dimensionValues)]);
        }
    }

    /**
     * Show the mapping which would be sent to the ElasticSearch server
     *
     * @return void
     */
    public function mappingCommand(): void
    {
        try {
            $nodeTypeMappingCollection = $this->nodeTypeMappingBuilder->buildMappingInformation($this->nodeIndexer->getIndex());
        } catch (\Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception $e) {
            $this->outputLine('Unable to get the current index');
            $this->sendAndExit(1);
        }
        foreach ($nodeTypeMappingCollection as $mapping) {
            /** @var Mapping $mapping */
            $this->output(Yaml::dump($mapping->asArray(), 5, 2));
            $this->outputLine();
        }
        $this->outputLine('------------');

        $mappingErrors = $this->nodeTypeMappingBuilder->getLastMappingErrors();
        if ($mappingErrors->hasErrors()) {
            $this->outputLine('<b>Mapping Errors</b>');
            foreach ($mappingErrors->getFlattenedErrors() as $errors) {
                foreach ($errors as $error) {
                    $this->outputLine('<error>%s</error>', [$error]);
                }
            }
        }

        if ($mappingErrors->hasWarnings()) {
            $this->outputLine('<b>Mapping Warnings</b>');
            foreach ($mappingErrors->getFlattenedWarnings() as $warnings) {
                foreach ($warnings as $warning) {
                    $this->outputLine('<comment>%s</comment>', [$warning]);
                }
            }
        }
    }
}
