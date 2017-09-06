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
     * @Flow\InjectConfiguration(path="configuration")
     */
    protected $settings;

    /**
     * @param NodeType $nodeType
     * @return bool
     * @throws Exception
     */
    public function isIndexable(NodeType $nodeType)
    {
        if (!isset($this->settings['nodeTypes'])) {
            return true;
        }

        if (!\is_array($this->settings['nodeTypes'])) {
            throw new Exception('Check your configuration at indexingConfiguration.nodeTypes, this path must be an array', 1504721629);
        }

        $settings = $this->settings['nodeTypes'];

        if (isset($settings[$nodeType->getName()]['indexed'])) {
            return $settings[$nodeType->getName()]['indexed'];
        }

        $nodeTypeParts = \explode(':', $nodeType->getName());
        $namespace = reset($nodeTypeParts) . ':*';
        if (isset($settings[$namespace]['indexed'])) {
            return $settings[$namespace]['indexed'];
        }
        if (isset($settings['*']['indexed'])) {
            return $settings['*']['indexed'];
        }

        return false;
    }
}
