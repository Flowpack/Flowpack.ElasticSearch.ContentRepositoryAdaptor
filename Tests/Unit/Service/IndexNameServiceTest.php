<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Unit\Service;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\IndexNameService;
use Neos\Flow\Tests\UnitTestCase;

class IndexNameServiceTest extends UnitTestCase
{

    public function indexNameDataProvider(): array
    {
        return [
            'simple' => [
                'indexNames' => ['neoscr-4f534b1eb0c1a785da31e681fb5e91ff-1582128256', 'neoscr-4f534b1eb0c1a785da31e681fb5e91ff-1582128111'],
                'postfix' => '1582128111',
                'expected' => ['neoscr-4f534b1eb0c1a785da31e681fb5e91ff-1582128111'],
            ],
            'prefixUsesDash' => [
                'indexNames' => ['neos-cr-4f534b1eb0c1a785da31e681fb5e91ff-1582128256', 'neos-cr-4f534b1eb0c1a785da31e681fb5e91ff-1582128111'],
                'postfix' => '1582128111',
                'expected' => ['neos-cr-4f534b1eb0c1a785da31e681fb5e91ff-1582128111'],
            ]
        ];
    }

    /**
     * @test
     * @dataProvider indexNameDataProvider
     *
     * @param array $indexNames
     * @param string $postfix
     * @param array $expected
     */
    public function filterIndexNamesByPostfix(array $indexNames, string $postfix, array $expected): void
    {
        self::assertEquals($expected, IndexNameService::filterIndexNamesByPostfix($indexNames, $postfix));
    }

}
