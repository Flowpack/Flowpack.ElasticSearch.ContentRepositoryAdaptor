<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional\Traits;

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

trait ContentRepositoryMultiDimensionNodeCreationTrait
{
    /**
     * @var WorkspaceRepository
     */
    protected $workspaceRepository;

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
        $defaultLanguageDimensionContext = $this->contextFactory->create([
            'workspaceName' => 'live',
            'dimensions' => ['language' => ['en_US']],
            'targetDimensions' => ['language' => 'en_US']
        ]);
        $deLanguageDimensionContext = $this->contextFactory->create([
            'workspaceName' => 'live',
            'dimensions' => ['language' => ['de']],
            'targetDimensions' => ['language' => 'de']
        ]);
        $dkLanguageDimensionContext = $this->contextFactory->create([
            'workspaceName' => 'live',
            'dimensions' => ['language' => ['dk']],
            'targetDimensions' => ['language' => 'dk']
        ]);

        $rootNode = $defaultLanguageDimensionContext->getRootNode();
        $this->siteNodeDefault = $rootNode->createNode('root', $this->nodeTypeManager->getNodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document'));
        $this->siteNodeDefault->setProperty('title', 'root-default');
        $this->siteNodeDe = $deLanguageDimensionContext->adoptNode($this->siteNodeDefault, true);
        $this->siteNodeDe->setProperty('title', 'root-de');
        $this->siteNodeDk = $dkLanguageDimensionContext->adoptNode($this->siteNodeDefault, true);
        $this->siteNodeDk->setProperty('title', 'root-dk');

        // add a document node that is translated in two languages
        $newDocumentNode1 = $this->siteNodeDefault->createNode('document1-default', $this->nodeTypeManager->getNodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document'));
        $newDocumentNode1->setProperty('title', 'document1-default');

        $translatedDocumentNode1De = $deLanguageDimensionContext->adoptNode($newDocumentNode1, true);
        $translatedDocumentNode1De->setProperty('title', 'document1-de');
        $translatedDocumentNode1Dk = $dkLanguageDimensionContext->adoptNode($newDocumentNode1, true);
        $translatedDocumentNode1Dk->setProperty('title', 'document1-dk');

        // add a document node that is not translated
        $newDocumentNode_untranslated = $this->siteNodeDefault->createNode('document-untranslated', $this->nodeTypeManager->getNodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document'));
        $newDocumentNode_untranslated->setProperty('title', 'document-untranslated');

        // add additional, but separate nodes here
        $standaloneDocumentNode2De = $this->siteNodeDe->createNode('document2-de', $this->nodeTypeManager->getNodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document'));
        $standaloneDocumentNode2De->setProperty('title', 'document2-de');

        $standaloneDocumentNode2Dk = $this->siteNodeDk->createNode('document2-dk', $this->nodeTypeManager->getNodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document'));
        $standaloneDocumentNode2Dk->setProperty('title', 'document2-dk');

        // add an additional german node
        $documentNodeDe3 = $standaloneDocumentNode2De->createNode('document3-de', $this->nodeTypeManager->getNodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document'));
        $documentNodeDe3->setProperty('title', 'document3-de');

        // add another german node, but translate it to danish
        $documentNodeDe4 = $standaloneDocumentNode2De->createNode('document4-de', $this->nodeTypeManager->getNodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document'));
        $documentNodeDe4->setProperty('title', 'document4-de');

        $translatedDocumentNode4Dk = $dkLanguageDimensionContext->adoptNode($documentNodeDe4, true);
        $translatedDocumentNode4Dk->setProperty('title', 'document4-dk');

        $this->persistenceManager->persistAll();
    }
}
