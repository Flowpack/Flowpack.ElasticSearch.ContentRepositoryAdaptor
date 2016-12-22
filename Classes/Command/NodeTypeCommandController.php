<?php
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

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

/**
 * Provides CLI features for debugging the node types.
 *
 * TODO: move to TYPO3CR or Neos
 *
 * @Flow\Scope("singleton")
 */
class NodeTypeCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var \Neos\ContentRepository\Domain\Service\NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * Show node type configuration after applying all supertypes etc
     *
     * @param string $nodeType the node type to optionally filter for
     * @return void
     */
    public function showCommand($nodeType = null)
    {
        if ($nodeType !== null) {
            /** @var \Neos\ContentRepository\Domain\Model\NodeType $nodeType */
            $nodeType = $this->nodeTypeManager->getNodeType($nodeType);
            $configuration = $nodeType->getFullConfiguration();
        } else {
            $nodeTypes = $this->nodeTypeManager->getNodeTypes();
            $configuration = [];
            /** @var \Neos\ContentRepository\Domain\Model\NodeType $nodeType */
            foreach ($nodeTypes as $nodeTypeName => $nodeType) {
                $configuration[$nodeTypeName] = $nodeType->getFullConfiguration();
            }
        }
        $this->output(\Symfony\Component\Yaml\Yaml::dump($configuration, 5, 2));
    }
}
