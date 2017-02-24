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

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cli\CommandController;

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
     * @var \TYPO3\TYPO3CR\Domain\Service\NodeTypeManager
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
            /** @var \TYPO3\TYPO3CR\Domain\Model\NodeType $nodeType */
            $nodeType = $this->nodeTypeManager->getNodeType($nodeType);
            $configuration = $nodeType->getFullConfiguration();
        } else {
            $nodeTypes = $this->nodeTypeManager->getNodeTypes();
            $configuration = [];
            /** @var \TYPO3\TYPO3CR\Domain\Model\NodeType $nodeType */
            foreach ($nodeTypes as $nodeTypeName => $nodeType) {
                $configuration[$nodeTypeName] = $nodeType->getFullConfiguration();
            }
        }
        $this->output(\Symfony\Component\Yaml\Yaml::dump($configuration, 5, 2));
    }
}
