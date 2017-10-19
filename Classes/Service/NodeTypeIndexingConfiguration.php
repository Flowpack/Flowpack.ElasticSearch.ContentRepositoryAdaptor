<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
final class NodeTypeIndexingConfiguration
{
    /**
     * @var array
     * @Flow\InjectConfiguration(path="configuration.nodeTypes", package="Neos.ContentRepository.Search")
     */
    protected $settings;

    /**
     * @param NodeType $nodeType
     * @return bool
     * @throws Exception
     */
    public function isIndexable(NodeType $nodeType)
    {
        if ($this->settings === null || !\is_array($this->settings)) {
            return true;
        }

        if (isset($this->settings[$nodeType->getName()]['indexed'])) {
            return (bool)$this->settings[$nodeType->getName()]['indexed'];
        }

        $nodeTypeParts = \explode(':', $nodeType->getName());
        $namespace = reset($nodeTypeParts) . ':*';
        if (isset($this->settings[$namespace]['indexed'])) {
            return (bool)$this->settings[$namespace]['indexed'];
        }
        if (isset($this->settings['*']['indexed'])) {
            return (bool)$this->settings['*']['indexed'];
        }

        return false;
    }
}
