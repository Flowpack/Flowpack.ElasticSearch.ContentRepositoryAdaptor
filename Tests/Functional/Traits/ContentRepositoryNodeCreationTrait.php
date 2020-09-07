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

trait ContentRepositoryNodeCreationTrait
{

    /**
     * Creates some sample nodes to run tests against
     */
    protected function createNodesForNodeSearchTest(): void
    {
        $this->context = $this->contextFactory->create([
            'workspaceName' => 'live',
            'dimensions' => ['language' => ['en_US']],
            'targetDimensions' => ['language' => 'en_US']
        ]);
        $rootNode = $this->context->getRootNode();

        $this->siteNode = $rootNode->createNode('welcome', $this->nodeTypeManager->getNodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document'));
        $this->siteNode->setProperty('title', 'welcome');

        $newDocumentNode1 = $this->siteNode->createNode('test-node-1', $this->nodeTypeManager->getNodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document'));
        $newDocumentNode1->setProperty('title', 'chicken');
        $newDocumentNode1->setProperty('title_analyzed', 'chicken');

        $newContentNode1 = $newDocumentNode1->getNode('main')->createNode('document-1-text-1', $this->nodeTypeManager->getNodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Content'));
        $newContentNode1->setProperty('text', 'A Scout smiles and whistles under all circumstances.');

        $newDocumentNode2 = $this->siteNode->createNode('test-node-2', $this->nodeTypeManager->getNodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document'));
        $newDocumentNode2->setProperty('title', 'chicken');
        $newDocumentNode2->setProperty('title_analyzed', 'chicken');

        // Nodes for cacheLifetime test
        $newContentNode2 = $newDocumentNode2->getNode('main')->createNode('document-2-text-1', $this->nodeTypeManager->getNodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Content'));
        $newContentNode2->setProperty('text', 'Hidden after 2025-01-01');
        $newContentNode2->setHiddenAfterDateTime(new \DateTime('@1735686000'));
        $newContentNode3 = $newDocumentNode2->getNode('main')->createNode('document-2-text-2', $this->nodeTypeManager->getNodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Content'));
        $newContentNode3->setProperty('text', 'Hidden before 2018-07-18');
        $newContentNode3->setHiddenBeforeDateTime(new \DateTime('@1531864800'));

        $newDocumentNode3 = $this->siteNode->createNode('test-node-3', $this->nodeTypeManager->getNodeType('Flowpack.ElasticSearch.ContentRepositoryAdaptor:Document'));
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
