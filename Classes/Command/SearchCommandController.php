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
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryBuilder;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryResult;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\SearchResultHelper;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\DimensionsService;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContentDimensionCombinator;
use Neos\ContentRepository\Domain\Service\ContextFactory;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Symfony\Component\Yaml\Yaml;

/**
 * @Flow\Scope("singleton")
 */
class SearchCommandController extends CommandController
{
    /**
     * @var ContextFactory
     * @Flow\Inject
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var ElasticSearchClient
     */
    protected $elasticSearchClient;

    /**
     * @param string $path
     * @param string $query
     * @param string|null $dimensions
     */
    public function fulltextCommand(string $path, string $query, ?string $dimensions = null)
    {
        $context = $this->createContext($dimensions);
        $contextNode = $context->getNode($path);

        if ($contextNode === null) {
            $this->outputLine('Context node not found');
            $this->sendAndExit(1);
        }

        $q = new ElasticSearchQueryBuilder();
        $q = $q->query($contextNode)
            ->log(__CLASS__)
            ->fulltext($query)
            ->limit(100)
            ->termSuggestions($query);

        /** @var ElasticSearchQueryResult $results */
        $results = $q->execute();

        $didYouMean = (new SearchResultHelper())->didYouMean($results);
        if (trim($didYouMean) !== '') {
            $this->outputLine('Did you mean <comment>%s</comment>', [$didYouMean]);
        }

        $this->outputLine();
        $this->outputLine('<info>Results</info>');
        $this->outputLine('Number of result(s): %d', [$q->count()]);
        $this->outputLine('Index name: %s', [$this->elasticSearchClient->getIndexName()]);
        $this->outputResults($q->execute());
        $this->outputLine();
    }

    /**
     * @param string $identifier
     * @param string|null $dimensions
     */
    public function viewNodeCommand(string $identifier, ?string $dimensions = null)
    {
        $context = $this->createContext($dimensions);

        $q = new ElasticSearchQueryBuilder();
        $q->query($context->getRootNode());
        $q->exactMatch('__identifier', $identifier);

        if ($q->count() > 0) {
            $this->outputLine();
            $this->outputLine('<info>Results</info>');
            $this->outputResults($q->execute());
        } else {
            $this->outputLine();
            $this->outputLine('No document matching the given node identifier');
        }
    }

    protected function outputResults(ElasticSearchQueryResult $result)
    {
        $results = array_map(function(NodeInterface $node) {
            $properties = [];
            foreach ($node->getProperties() as $propertyName => $propertyValue) {
                $properties[$propertyName] = '<b>' . $propertyName . '</b>: ' . (string)$propertyValue;
            }
            return [
                'identifier' => $node->getIdentifier(),
                'label' => $node->getLabel(),
                'nodeType' => $node->getNodeType()->getName(),
                'contextPath' => implode(PHP_EOL, explode('@', $node->getContextPath())),
                'properties' => implode(PHP_EOL, $properties),
            ];
        }, $result->toArray());

        $this->output->outputTable($results, ['Identifier', 'Label', 'Node Type', 'Context', 'Properties']);
    }

    protected function createContext(string $dimensions = null)
    {
        $contextConfiguration = [
            'workspaceName' => 'live',
        ];
        if ($dimensions !== null) {
            $contextConfiguration['dimensions'] = json_decode($dimensions, true);
        }

        $context = $this->contextFactory->create($contextConfiguration);
        return $context;
    }
}
