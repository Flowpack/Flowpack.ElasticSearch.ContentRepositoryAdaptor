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
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\QueryBuildingException;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\DimensionsService;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContentDimensionCombinator;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\ContextFactory;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
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
     * @throws Exception
     * @throws QueryBuildingException
     * @throws IllegalObjectTypeException
     */
    public function fulltextCommand(string $path, string $query, ?string $dimensions = null): void
    {
        $context = $this->createContext($dimensions);
        $contextNode = $context->getNode($path);

        if ($contextNode === null) {
            $this->outputLine('Context node not found');
            $this->sendAndExit(1);
        }

        $queryBuilder = new ElasticSearchQueryBuilder();
        $queryBuilder = $queryBuilder->query($contextNode)
            ->log(__CLASS__)
            ->fulltext($query)
            ->limit(100)
            ->termSuggestions($query);

        /** @var ElasticSearchQueryResult $results */
        $results = $queryBuilder->execute();

        $didYouMean = (new SearchResultHelper())->didYouMean($results);
        if (trim($didYouMean) !== '') {
            $this->outputLine('Did you mean <comment>%s</comment>', [$didYouMean]);
        }

        $this->outputLine();
        $this->outputLine('<info>Results</info>');
        $this->outputLine('Number of result(s): %d', [$queryBuilder->count()]);
        $this->outputLine('Index name: %s', [$this->elasticSearchClient->getIndexName()]);
        $this->outputResults($results);
        $this->outputLine();
    }

    /**
     * @param string $identifier
     * @param string|null $dimensions
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws QueryBuildingException
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws \Neos\Flow\Http\Exception
     */
    public function viewNodeCommand(string $identifier, ?string $dimensions = null): void
    {
        $context = $this->createContext($dimensions);

        $queryBuilder = new ElasticSearchQueryBuilder();
        $queryBuilder->query($context->getRootNode());
        $queryBuilder->exactMatch('__identifier', $identifier);

        if ($queryBuilder->count() > 0) {
            $this->outputLine();
            $this->outputLine('<info>Results</info>');
            $this->outputResults($queryBuilder->execute());
        } else {
            $this->outputLine();
            $this->outputLine('No document matching the given node identifier');
        }
    }

    /**
     * @param ElasticSearchQueryResult $result
     */
    private function outputResults(ElasticSearchQueryResult $result): void
    {
        $results = array_map(static function (NodeInterface $node) {
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

    /**
     * @param string|null $dimensions
     * @return Context
     */
    private function createContext(string $dimensions = null): Context
    {
        $contextConfiguration = [
            'workspaceName' => 'live',
        ];
        if ($dimensions !== null) {
            $contextConfiguration['dimensions'] = json_decode($dimensions, true);
        }

        return $this->contextFactory->create($contextConfiguration);
    }
}
