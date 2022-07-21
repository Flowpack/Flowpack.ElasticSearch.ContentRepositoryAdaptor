<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Unit\Service;

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\DimensionsService;
use Neos\Flow\Tests\UnitTestCase;

class DimensionServiceTest extends UnitTestCase
{
    /**
     * @var DimensionsService
     */
    protected $dimensionService;

    public function setUp(): void
    {
        $proxyClass = $this->buildAccessibleProxy(DimensionsService::class);
        $this->dimensionService = new $proxyClass;
    }

    public function dimensionCombinationProvider(): array
    {
        return [
            'multiDimension' => [
                'dimensionCombinations' => [
                    [
                        'country' => ['uk', 'us'],
                        'language' => ['en_UK', 'en_US'],
                    ],
                    [
                        'country' => ['us'],
                        'language' => ['en_US'],
                    ],
                    [
                        'country' => ['de'],
                        'language' => ['de'],
                    ]
                ],
                'nodeDimensions' => [
                    'country' => ['us'],
                    'language' => ['en_US'],
                ],
                'expected' => [
                    [
                        'country' => ['uk', 'us'],
                        'language' => ['en_UK', 'en_US'],
                    ],
                    [
                        'country' => ['us'],
                        'language' => ['en_US'],
                    ]
                ]
            ],
            'singleDimension' => [
                'dimensionCombinations' => [
                    [
                        'language' => ['en_UK', 'en_US'],
                    ],
                    [
                        'language' => ['en_US'],
                    ],
                    [
                        'language' => ['de'],
                    ]
                ],
                'nodeDimensions' => [
                    'language' => ['en_US'],
                ],
                'expected' => [
                    [
                        'language' => ['en_UK', 'en_US'],
                    ],
                    [
                        'language' => ['en_US'],
                    ]
                ]
            ]
        ];
    }

    /**
     * @test
     *
     * @dataProvider dimensionCombinationProvider
     * @param array $dimensionCombinations
     * @param array $nodeDimensions
     * @param array $expected
     */
    public function reduceDimensionCombinationstoSelfAndFallback(array $dimensionCombinations, array $nodeDimensions, array $expected): void
    {
        self::assertEquals($expected, $this->dimensionService->_call('reduceDimensionCombinationstoSelfAndFallback', $dimensionCombinations, $nodeDimensions));
    }
}
