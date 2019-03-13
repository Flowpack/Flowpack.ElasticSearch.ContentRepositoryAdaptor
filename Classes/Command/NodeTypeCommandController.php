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
     * Show node type configuration after applying all supertypes etc
     *
     * @param string $nodeType the node type to optionally filter for
     * @return void
     * @throws NodeTypeNotFoundException
     */
    public function showCommand(string $nodeType = null): void
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
}
