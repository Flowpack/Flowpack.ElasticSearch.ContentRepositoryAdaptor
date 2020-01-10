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
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\ContextFactory;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Media\Domain\Model\ResourceBasedInterface;

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
     * This commnd can be used to test and debug
     * full-text searches
     *
     * @param string $searchWord The search word to seartch for.
     * @param string $path Path to the root node. Defaults to '/'
     * @param string|null $dimensions The dimesnions to be taken into account.
     * @throws Exception
     * @throws QueryBuildingException
     * @throws IllegalObjectTypeException
     */
    public function fulltextCommand(string $searchWord, string $path = '/', ?string $dimensions = null): void
    {
        $context = $this->createContext($dimensions);
        $contextNode = $context->getNode($path);

        if ($contextNode === null) {
            $this->outputLine('Context node not found');
            $this->sendAndExit(1);
        }

        $queryBuilder = new ElasticSearchQueryBuilder();
        $queryBuilder = $queryBuilder->query($contextNode)
            ->fulltext($searchWord)
            ->limit(100)
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
                if ($propertyValue instanceof ResourceBasedInterface) {
                    $properties[$propertyName] = '<b>' . $propertyName . '</b>: ' . (string)$propertyValue->getResource()->getFilename();
                } elseif ($propertyValue instanceof \DateTime) {
                    $properties[$propertyName] = '<b>' . $propertyName . '</b>: ' . $propertyValue->format('Y-m-d H:i');
                } else {
                    $properties[$propertyName] = '<b>' . $propertyName . '</b>: ' . (string)$propertyValue;
                }
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
