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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\DocumentIdentifier\DocumentIdentifierGeneratorInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Neos\Domain\Service\NodeTypeNameFactory;

/**
 * Abstract Fulltext Indexer Driver
 */
abstract class AbstractIndexerDriver extends AbstractDriver
{
    /**
     * @Flow\Inject
     * @var NodeTypeMappingBuilderInterface
     */
    protected $nodeTypeMappingBuilder;

    /**
     * @Flow\Inject
     * @var DocumentIdentifierGeneratorInterface
     */
    protected $documentIdentifierGenerator;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    /**
     * Whether the node is configured as fulltext root.
     *
     * @param Node $node
     * @return bool
     */
    protected function isFulltextRoot(Node $node): bool
    {
        $nodeType = $this->contentRepositoryRegistry->get($node->contentRepositoryId)->getNodeTypeManager()->getNodeType($node->nodeTypeName);

        if ($nodeType->hasConfiguration('search')) {
            $elasticSearchSettingsForNode = $nodeType->getConfiguration('search');
            if (isset($elasticSearchSettingsForNode['fulltext']['isRoot']) && $elasticSearchSettingsForNode['fulltext']['isRoot'] === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Node $node
     * @return Node|null
     */
    protected function findClosestFulltextRoot(Node $node): ?Node
    {
        $subgraph = $this->contentRepositoryRegistry->get($node->contentRepositoryId)->getContentGraph($node->workspaceName)->getSubgraph($node->dimensionSpacePoint, VisibilityConstraints::withoutRestrictions());

        $closestFulltextNode = $node;
        while (!$this->isFulltextRoot($closestFulltextNode)) {
            $closestFulltextNode = $subgraph->findParentNode($closestFulltextNode->aggregateId);
            if ($closestFulltextNode === null) {
                // root of hierarchy, no fulltext root found anymore, abort silently...
                if (!$node->nodeTypeName->equals(NodeTypeNameFactory::forRoot()) &&
                    !$node->nodeTypeName->equals(NodeTypeNameFactory::forSites())) {
                    $this->logger->warning(sprintf('NodeIndexer: No fulltext root found for node %s', (string)$node->aggregateId), LogEnvironment::fromMethodName(__METHOD__));
                }

                return null;
            }
        }

        return $closestFulltextNode;
    }
}
