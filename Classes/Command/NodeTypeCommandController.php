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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\NodeTypeIndexingConfiguration;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides CLI features for debugging the node types.
 *
 * TODO: move to ContentRepository or Neos
 *
 * @Flow\Scope("singleton")
 */
class NodeTypeCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var NodeTypeIndexingConfiguration
     */
    protected $nodeTypeIndexingConfiguration;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    /**
     * Show node type configuration after applying all supertypes etc
     *
     * @param string $nodeType the node type to optionally filter for
     * @return void
     */
    public function showCommand(string $contentRepository = 'default', ?string $nodeType = null): void
    {
        $contentRepositoryId = ContentRepositoryId::fromString($contentRepository);
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $nodeTypeManager = $contentRepository->getNodeTypeManager();

        if ($nodeType !== null) {
            $nodeType = $nodeTypeManager->getNodeType(NodeTypeName::fromString($nodeType));
            $configuration = $nodeType->getFullConfiguration();
        } else {
            $nodeTypes = $nodeTypeManager->getNodeTypes();
            $configuration = [];
            foreach ($nodeTypes as $nodeTypeName => $nodeType) {
                $configuration[$nodeTypeName] = $nodeType->getFullConfiguration();
            }
        }
        $this->output(Yaml::dump($configuration, 5, 2));
    }

    /**
     * Shows a list of NodeTypes and if they are configured to be indexable or not
     *
     * @throws \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception
     */
    public function showIndexableConfigurationCommand(string $contentRepository = 'default'): void
    {
        $contentRepositoryId = ContentRepositoryId::fromString($contentRepository);

        $indexableConfiguration = $this->nodeTypeIndexingConfiguration->getIndexableConfiguration($contentRepositoryId);
        $indexTable = [];
        foreach ($indexableConfiguration as $nodeTypeName => $value) {
            $indexTable[] = [$nodeTypeName, $value ? '<success>true</success>' : '<error>false</error>'];
        }

        $this->output->outputTable($indexTable, ['NodeType', 'indexable']);
    }
}
