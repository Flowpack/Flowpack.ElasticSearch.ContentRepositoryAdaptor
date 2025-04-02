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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryBuilder;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryResult;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\SearchResultHelper;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\QueryBuildingException;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Media\Domain\Model\ResourceBasedInterface;
use Neos\Neos\Domain\NodeLabel\NodeLabelGeneratorInterface;
use Neos\Neos\Domain\Service\NodeTypeNameFactory;
use Neos\Utility\Arrays;

/**
 * @Flow\Scope("singleton")
 */
class SearchCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var ElasticSearchClient
     */
    protected $elasticSearchClient;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected NodeLabelGeneratorInterface $nodeLabelGenerator;

    /**
     * This commnd can be used to test and debug
     * full-text searches
     *
     * @param string $searchWord The search word to seartch for.
     * @param string $contentRepository
     * @param string $path Path to the root node. Defaults to '/'
     * @param string|null $dimensions The dimesnions to be taken into account.
     * @throws Exception
     * @throws QueryBuildingException
     * @throws IllegalObjectTypeException
     * @throws Exception\ConfigurationException
     */
    public function fulltextCommand(string $searchWord, $contentRepository = 'default', ?string $nodeAggregateId = null, ?string $dimensions = null): void
    {
        if ($dimensions !== null && is_array(json_decode($dimensions, true)) === false) {
            $this->outputLine('<error>Error: </error>The Dimensions must be given as a JSON array like \'{"language":["de"]}\'');
            $this->sendAndExit(1);
        }

        $contentRepositoryId = ContentRepositoryId::fromString($contentRepository);
        $dimensionSpacePoint = $dimensions ? DimensionSpacePoint::fromJsonString($dimensions) : DimensionSpacePoint::createWithoutDimensions();

        $contentGraph = $this->contentRepositoryRegistry->get($contentRepositoryId)->getContentGraph(WorkspaceName::forLive());
        $subgraph = $contentGraph->getSubgraph($dimensionSpacePoint, VisibilityConstraints::withoutRestrictions());

        if ($nodeAggregateId !== null) {
            $contextNode = $subgraph->findNodeById(NodeAggregateId::fromString($nodeAggregateId));
        } else {
            $contextNode = $subgraph->findRootNodeByType(NodeTypeNameFactory::forSites());
        }

        if ($contextNode === null) {
            $this->outputLine('Context node not found');
            $this->sendAndExit(1);
        }

        $queryBuilder = new ElasticSearchQueryBuilder();
        $queryBuilder = $queryBuilder->query($contextNode)
            ->fulltext($searchWord)
            ->limit(10)
            ->termSuggestions($searchWord)
            ->log(__CLASS__);

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
     * Prints the index content of the given node identifier.
     *
     * @param string $identifier The node identifier
     * @param string $contentRepository
     * @param string|null $dimensions Dimensions, specified in JSON format, like '{"language":"en"}'
     * @param string $field Name or path to a source field to display. Eg. "__fulltext.h1"
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws QueryBuildingException
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws \Neos\Flow\Http\Exception
     */
    public function viewNodeCommand(string $identifier, string $contentRepository = 'default', ?string $dimensions = null, string $field = ''): void
    {
        if ($dimensions !== null && is_array(json_decode($dimensions, true)) === false) {
            $this->outputLine('<error>Error: </error>The Dimensions must be given as a JSON array like \'{"language":["de"]}\'');
            $this->sendAndExit(1);
        }

        $contentRepositoryId = ContentRepositoryId::fromString($contentRepository);
        $dimensionSpacePoint = $dimensions ? DimensionSpacePoint::fromJsonString($dimensions) : DimensionSpacePoint::createWithoutDimensions();

        $contentGraph = $this->contentRepositoryRegistry->get($contentRepositoryId)->getContentGraph(WorkspaceName::forLive());
        $subgraph = $contentGraph->getSubgraph($dimensionSpacePoint, VisibilityConstraints::withoutRestrictions());

        $rootNode = $subgraph->findRootNodeByType(NodeTypeNameFactory::forSites());

        if ($rootNode === null) {
            $this->outputLine('<error>Error: </error>No root node found for the given dimensions');
            return;
        }

        $queryBuilder = new ElasticSearchQueryBuilder();
        $queryBuilder->query($rootNode);
        $queryBuilder->exactMatch('neos_node_identifier', $identifier);

        $queryBuilder->getRequest()->setValueByPath('_source', []);

        if ($queryBuilder->count() > 0) {
            $this->outputLine();
            $this->outputLine('<info>Results</info>');

            foreach ($queryBuilder->execute() as $node) {
                $this->outputLine('<b>%s</b>', [(string)$node->aggregateId->value]);
                $data = $queryBuilder->getFullElasticSearchHitForNode($node);

                if ($field !== '') {
                    $data = Arrays::getValueByPath($data, '_source.' . $field);
                }

                $this->outputLine(print_r($data, true));
                $this->outputLine();
            }
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
        $results = array_map(function (Node $node) {
            $properties = [];

            foreach ($node->properties as $propertyName => $propertyValue) {
                if ($propertyValue instanceof ResourceBasedInterface) {
                    $properties[$propertyName] = '<b>' . $propertyName . '</b>: ' . (string)$propertyValue->getResource()->getFilename();
                } elseif ($propertyValue instanceof \DateTimeInterface) {
                    $properties[$propertyName] = '<b>' . $propertyName . '</b>: ' . $propertyValue->format('Y-m-d H:i');
                } elseif (is_array($propertyValue)) {
                    $properties[$propertyName] = '<b>' . $propertyName . '</b>: ' . 'array';
                } elseif ($propertyValue instanceof Node) {
                    $properties[$propertyName] = '<b>' . $propertyName . '</b>: ' . $propertyValue->aggregateId->value;
                } else {
                    $properties[$propertyName] = '<b>' . $propertyName . '</b>: ' . (string)$propertyValue;
                }
            }

            return [
                'identifier' => $node->aggregateId->value,
                'label' => $this->nodeLabelGenerator->getLabel($node),
                'nodeType' => $node->nodeTypeName->value,
                'properties' => implode(PHP_EOL, $properties),
            ];
        }, $result->toArray());

        $this->output->outputTable($results, ['Identifier', 'Label', 'Node Type', 'Properties']);
    }
}
