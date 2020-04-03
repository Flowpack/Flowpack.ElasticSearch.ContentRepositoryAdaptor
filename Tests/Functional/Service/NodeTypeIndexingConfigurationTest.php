<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional\Service;

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
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Tests\FunctionalTestCase;

class NodeTypeIndexingConfigurationTest extends FunctionalTestCase
{

    /**
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @var NodeTypeIndexingConfiguration
     */
    protected $nodeTypeIndexingConfiguration;

    public function setUp(): void
    {
        parent::setUp();
        $this->nodeTypeManager = $this->objectManager->get(NodeTypeManager::class);
        $this->nodeTypeIndexingConfiguration = $this->objectManager->get(NodeTypeIndexingConfiguration::class);
    }

    public function nodeTypeDataProvider(): array
    {
        return [
            'notIndexable' => [
                'nodeTypeName' => 'Flowpack.ElasticSearch.ContentRepositoryAdaptor:Type1',
                'expected' => false,
            ],
            'indexable' => [
                'nodeTypeName' => 'Flowpack.ElasticSearch.ContentRepositoryAdaptor:Type2',
                'expected' => true,
            ],
        ];
    }

    /**
     * @test
     * @dataProvider nodeTypeDataProvider
     *
     * @param string $nodeTypeName
     * @param bool $expected
     * @throws \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception
     * @throws \Neos\ContentRepository\Exception\NodeTypeNotFoundException
     */
    public function isIndexable(string $nodeTypeName, bool $expected): void
    {
        self::assertEquals($expected, $this->nodeTypeIndexingConfiguration->isIndexable($this->nodeTypeManager->getNodeType($nodeTypeName)));
    }

    /**
     * @test
     * @dataProvider nodeTypeDataProvider
     *
     * @param string $nodeTypeName
     * @param bool $expected
     * @throws \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception
     */
    public function getIndexableConfiguration(string $nodeTypeName, bool $expected): void
    {
        $indexableConfiguration = $this->nodeTypeIndexingConfiguration->getIndexableConfiguration();
        self::assertEquals($indexableConfiguration[$nodeTypeName], $expected);
    }
}
