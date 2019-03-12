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

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Model\NodeInterface;

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
     * Whether the node is configured as fulltext root.
     *
     * @param NodeInterface $node
     * @return bool
     */
    protected function isFulltextRoot(NodeInterface $node): bool
    {
        if ($node->getNodeType()->hasConfiguration('search')) {
            $elasticSearchSettingsForNode = $node->getNodeType()->getConfiguration('search');
            if (isset($elasticSearchSettingsForNode['fulltext']['isRoot']) && $elasticSearchSettingsForNode['fulltext']['isRoot'] === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param NodeInterface $node
     * @return NodeInterface|null
     */
    protected function findClosestFulltextRoot(NodeInterface $node)
    {
        $closestFulltextNode = $node;
        while (!$this->isFulltextRoot($closestFulltextNode)) {
            $closestFulltextNode = $closestFulltextNode->getParent();
            if ($closestFulltextNode === null) {
                // root of hierarchy, no fulltext root found anymore, abort silently...
                if ($node->getPath() !== '/' && $node->getPath() !== '/sites') {
                    $this->logger->log(sprintf('NodeIndexer: No fulltext root found for node %s (%s)', $node->getIdentifier(), $node->getContextPath()), LOG_WARNING, null, 'ElasticSearch (CR)');
                }

                return null;
            }
        }

        return $closestFulltextNode;
    }
}
