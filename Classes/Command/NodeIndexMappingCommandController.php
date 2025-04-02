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
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
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
     * @var NodeTypeMappingBuilderInterface
     */
    protected $nodeTypeMappingBuilder;

    #[Flow\Inject()]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    /**
     * Shows the mapping between dimensions presets and index name
     *
     * @throws Exception
     */
    public function indicesCommand(string $contentRepository = 'default'): void
    {
        $indexName = $this->nodeIndexer->getIndexName();

        $headers = ['Dimension Preset', 'Index Name'];
        $rows = [];

        $contentRepositoryId = ContentRepositoryId::fromString($contentRepository);
        $variationGraph = $this->contentRepositoryRegistry->get($contentRepositoryId)->getVariationGraph();

        foreach ($variationGraph->getDimensionSpacePoints() as $dimensionSpacePoint) {
            $rows[] = [
                $dimensionSpacePoint->toJson(),
                sprintf('%s-%s', $indexName, $dimensionSpacePoint->hash)
            ];
        }

        $this->output->outputTable($rows, $headers);
    }

    /**
     * Show the mapping which would be sent to the ElasticSearch server
     *
     * @return void
     * @throws \Flowpack\ElasticSearch\Exception
     */
    public function mappingCommand(string $contentRepository = 'default'): void
    {
        $contentRepositoryId = ContentRepositoryId::fromString($contentRepository);
        try {
            $nodeTypeMappingCollection = $this->nodeTypeMappingBuilder->buildMappingInformation($contentRepositoryId, $this->nodeIndexer->getIndex());
        } catch (Exception $e) {
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
