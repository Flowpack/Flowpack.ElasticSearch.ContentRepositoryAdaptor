<?php
declare(strict_types=1);

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
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
final class NodeTypeIndexingConfiguration
{
    /**
     * @var array
     * @Flow\InjectConfiguration(path="defaultConfigurationPerNodeType", package="Neos.ContentRepository.Search")
     */
    protected $settings;

    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @param NodeType $nodeType
     * @return bool
     * @throws Exception
     */
    public function isIndexable(NodeType $nodeType): bool
    {
        if (!is_array($this->settings)) {
            return true;
        }

        if (isset($this->settings[$nodeType->getName()]['indexed'])) {
            return (bool)$this->settings[$nodeType->getName()]['indexed'];
        }

        $nodeTypeParts = explode(':', $nodeType->getName());
        $namespace = reset($nodeTypeParts) . ':*';
        if (isset($this->settings[$namespace]['indexed'])) {
            return (bool)$this->settings[$namespace]['indexed'];
        }
        if (isset($this->settings['*']['indexed'])) {
            return (bool)$this->settings['*']['indexed'];
        }

        return false;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getIndexableConfiguration(): array
    {
        $nodeConfigurations = [];
        /** @var NodeType $nodeType */
        foreach ($this->nodeTypeManager->getNodeTypes(false) as $nodeType) {
            $nodeConfigurations[$nodeType->getName()] = $this->isIndexable($nodeType);
        }

        return $nodeConfigurations;
    }
}
