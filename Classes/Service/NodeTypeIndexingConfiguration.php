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
use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
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

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    /**
     * @param NodeType $nodeType
     * @return bool
     * @throws Exception
     */
    public function isIndexable(NodeType $nodeType): bool
    {
        if ($this->settings === null || !is_array($this->settings)) {
            return true;
        }

        if (isset($this->settings[$nodeType->name->value]['indexed'])) {
            return (bool)$this->settings[$nodeType->name->value]['indexed'];
        }

        $nodeTypeParts = explode(':', $nodeType->name->value);
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
    public function getIndexableConfiguration(ContentRepositoryId $contentRepositoryId): array
    {
        $nodeConfigurations = [];
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        /** @var NodeType $nodeType */
        foreach ($contentRepository->getNodeTypeManager()->getNodeTypes(false) as $nodeType) {
            $nodeConfigurations[$nodeType->name->value] = $this->isIndexable($nodeType);
        }

        return $nodeConfigurations;
    }
}
