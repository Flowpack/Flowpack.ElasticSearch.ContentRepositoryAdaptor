<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional\TRaits;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\Transfer\Exception\ApiException;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Exception\NodeExistsException;
use Neos\ContentRepository\Exception\NodeTypeNotFoundException;
use Neos\Flow\Cli\Exception\StopCommandException;
use Neos\Flow\Mvc\Exception\StopActionException;

trait ContentRepositoryNodeCreationTrait
{
    /**
     * @var WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var NodeInterface
     */
    protected $siteNode;

    /**
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @var NodeDataRepository
     */
    protected $nodeDataRepository;

    private function setupContentRepository():void
    {
        $this->workspaceRepository = $this->objectManager->get(WorkspaceRepository::class);
        $liveWorkspace = new Workspace('live');
        $this->workspaceRepository->add($liveWorkspace);

        $this->nodeTypeManager = $this->objectManager->get(NodeTypeManager::class);
        $this->contextFactory = $this->objectManager->get(ContextFactoryInterface::class);
        $this->context = $this->contextFactory->create([
            'workspaceName' => 'live',
            'dimensions' => ['language' => ['en_US']],
            'targetDimensions' => ['language' => 'en_US']
        ]);
        $rootNode = $this->context->getRootNode();

        $this->siteNode = $rootNode->createNode('welcome', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $this->siteNode->setProperty('title', 'welcome');

        $this->nodeDataRepository = $this->objectManager->get(NodeDataRepository::class);
    }

    /**
     * Creates some sample nodes to run tests against
     *
     * @throws NodeExistsException
     * @throws NodeTypeNotFoundException
     * @throws StopActionException
     * @throws \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception
     * @throws ApiException
     * @throws StopCommandException
     */
    protected function createNodesForNodeSearchTest(): void
    {
        $newDocumentNode1 = $this->siteNode->createNode('test-node-1', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $newDocumentNode1->setProperty('title', 'chicken');
        $newDocumentNode1->setProperty('title_analyzed', 'chicken');

        $newContentNode1 = $newDocumentNode1->getNode('main')->createNode('document-1-text-1', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Text'));
        $newContentNode1->setProperty('text', 'A Scout smiles and whistles under all circumstances.');

        $newDocumentNode2 = $this->siteNode->createNode('test-node-2', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $newDocumentNode2->setProperty('title', 'chicken');
        $newDocumentNode2->setProperty('title_analyzed', 'chicken');

        // Nodes for cacheLifetime test
        $newContentNode2 = $newDocumentNode2->getNode('main')->createNode('document-2-text-1', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Text'));
        $newContentNode2->setProperty('text', 'Hidden after 2025-01-01');
        $newContentNode2->setHiddenAfterDateTime(new \DateTime('@1735686000'));
        $newContentNode3 = $newDocumentNode2->getNode('main')->createNode('document-2-text-2', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Text'));
        $newContentNode3->setProperty('text', 'Hidden before 2018-07-18');
        $newContentNode3->setHiddenBeforeDateTime(new \DateTime('@1531864800'));

        $newDocumentNode3 = $this->siteNode->createNode('test-node-3', $this->nodeTypeManager->getNodeType('Neos.NodeTypes:Page'));
        $newDocumentNode3->setProperty('title', 'egg');
        $newDocumentNode3->setProperty('title_analyzed', 'egg');

        $dimensionContext = $this->contextFactory->create([
            'workspaceName' => 'live',
            'dimensions' => ['language' => ['de']]
        ]);
        $translatedNode3 = $dimensionContext->adoptNode($newDocumentNode3, true);
        $translatedNode3->setProperty('title', 'De');

        $this->persistenceManager->persistAll();
    }
}
