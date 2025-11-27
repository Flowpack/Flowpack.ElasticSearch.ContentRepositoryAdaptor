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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\NodeTypeIndexingConfiguration;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Exception\NodeTypeNotFoundException;
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
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @Flow\Inject
     * @var NodeTypeIndexingConfiguration
     */
    protected $nodeTypeIndexingConfiguration;

    /**
     * Show node type configuration after applying all supertypes etc
     *
     * @param string|null $nodeType the node type to optionally filter for
     * @return void
     * @throws NodeTypeNotFoundException
     */
    public function showCommand(?string $nodeType = null): void
    {
        if ($nodeType !== null) {
            /** @var NodeType $nodeType */
            $nodeType = $this->nodeTypeManager->getNodeType($nodeType);
            $configuration = $nodeType->getFullConfiguration();
        } else {
            $nodeTypes = $this->nodeTypeManager->getNodeTypes();
            $configuration = [];
            /** @var NodeType $nodeType */
            foreach ($nodeTypes as $nodeTypeName => $nodeType) {
                $configuration[$nodeTypeName] = $nodeType->getFullConfiguration();
            }
        }
        $this->output(Yaml::dump($configuration, 5, 2));
    }

    /**
     * Shows a list of NodeTypes and if they are configured to be indexable or not
     *
     * @throws Exception
     */
    public function showIndexableConfigurationCommand(): void
    {
        $indexableConfiguration = $this->nodeTypeIndexingConfiguration->getIndexableConfiguration();
        $indexTable = [];
        foreach ($indexableConfiguration as $nodeTypeName => $value) {
            $indexTable[] = [$nodeTypeName, $value ? '<success>true</success>' : '<error>false</error>'];
        }

        $this->output->outputTable($indexTable, ['NodeType', 'indexable']);
    }
}
