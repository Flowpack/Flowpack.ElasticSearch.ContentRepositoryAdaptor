<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Unit\Eel;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version1\Query\FilteredQuery;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryBuilder;
use TYPO3\Flow\Tests\UnitTestCase;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Model\Workspace;
use TYPO3\TYPO3CR\Domain\Service\Context;

/**
 * Testcase for ElasticSearchQueryBuilder
 */
class ElasticSearchQueryBuilderTest extends UnitTestCase
{
    /**
     * @var ElasticSearchQueryBuilder
     */
    protected $queryBuilder;

    public function setUp()
    {
        /** @var NodeInterface|\PHPUnit_Framework_MockObject_MockObject $node */
        $node = $this->createMock(NodeInterface::class);
        $node->expects($this->any())->method('getPath')->will($this->returnValue('/foo/bar'));
        $mockContext = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $mockContext->expects($this->any())->method('getDimensions')->will($this->returnValue([]));
        $node->expects($this->any())->method('getContext')->will($this->returnValue($mockContext));

        $mockWorkspace = $this->getMockBuilder(Workspace::class)->disableOriginalConstructor()->getMock();
        $mockContext->expects($this->any())->method('getWorkspace')->will($this->returnValue($mockWorkspace));

        $mockWorkspace->expects($this->any())->method('getName')->will($this->returnValue('user-foo'));

        $this->queryBuilder = new ElasticSearchQueryBuilder();

        $request = [
           'query' => [
               'filtered' => [
                   'query' => [
                       'bool' => [
                           'must' => [
                               ['match_all' => []]
                           ]
                       ]
                   ],
                   'filter' => [
                       'bool' => [
                           'must' => [],
                           'should' => [],
                           'must_not' => [
                               [
                                   'term' => ['_hidden' => true]
                               ],
                               [
                                   'range' => [
                                       '_hiddenBeforeDateTime' => [
                                           'gt' => 'now'
                                       ]
                                   ]
                               ],
                               [
                                   'range' => [
                                       '_hiddenAfterDateTime' => [
                                           'lt' => 'now'
                                       ]
                                   ]
                               ]
                           ]
                       ]
                   ]
               ]
           ],
            'fields' => ['__path']
        ];
        $unsupportedFieldsInCountRequest = ['fields', 'sort', 'from', 'size', 'highlight', 'aggs', 'aggregations'];

        $this->inject($this->queryBuilder, 'request', new FilteredQuery($request, $unsupportedFieldsInCountRequest));

        $query = new FilteredQuery($this->queryBuilder->getRequest()->toArray(), []);
        $this->inject($this->queryBuilder, 'request', $query);
        $this->queryBuilder->query($node);
    }

    /**
     * @test
     */
    public function basicRequestStructureTakesContextNodeIntoAccount()
    {
        $expected = [
            'query' => [
                'filtered' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['match_all' => []]
                            ]
                        ]
                    ],
                    'filter' => [
                        'bool' => [
                            'must' => [
                                0 => [
                                    'bool' => [
                                        'should' => [
                                            0 => [
                                                'term' => [
                                                    '__parentPath' => '/foo/bar'
                                                ]
                                            ],
                                            1 => [
                                                'term' => [
                                                    '__path' => '/foo/bar'
                                                ]
                                            ]
                                        ]
                                    ]
                                ],
                                1 => [
                                    'terms' => [
                                        '__workspace' => ['live', 'user-foo']
                                    ]
                                ],
                                2 => [
                                    'term' => [
                                        '__dimensionCombinationHash' => 'd751713988987e9331980363e24189ce'
                                    ]
                                ]
                            ],
                            'should' => [],
                            'must_not' => [
                                // Filter out all hidden elements
                                [
                                    'term' => ['_hidden' => true]
                                ],
                                // if now < hiddenBeforeDateTime: HIDE
                                // -> hiddenBeforeDateTime > now
                                [
                                    'range' => [
                                        '_hiddenBeforeDateTime' => [
                                            'gt' => 'now'
                                        ]
                                    ]
                                ],
                                [
                                    'range' => [
                                        '_hiddenAfterDateTime' => [
                                            'lt' => 'now'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'fields' => ['__path']
        ];
        $actual = $this->queryBuilder->getRequest()->toArray();
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * @expectedException \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\QueryBuildingException
     */
    public function queryFilterThrowsExceptionOnInvalidClauseType()
    {
        $this->queryBuilder->queryFilter('foo', [], 'unsupported');
    }

    /**
     * @test
     */
    public function nodeTypeFilterWorks()
    {
        $this->queryBuilder->nodeType('Foo.Bar:Baz');
        $expected = [
            'term' => [
                '__typeAndSupertypes' => 'Foo.Bar:Baz'
            ]
        ];
        $actual = $this->queryBuilder->getRequest()->toArray();
        $this->assertInArray($expected, $actual['query']['filtered']['filter']['bool']['must']);
    }

    /**
     * @test
     */
    public function sortAscWorks()
    {
        $this->queryBuilder->sortAsc('fieldName');
        $expected = [
            [
                'fieldName' => ['order' => 'asc']
            ]
        ];
        $actual = $this->queryBuilder->getRequest()->toArray();
        $this->assertSame($expected, $actual['sort']);
    }

    /**
     * @test
     */
    public function sortingIsAdditive()
    {
        $this->queryBuilder->sortAsc('fieldName')->sortDesc('field2')->sortAsc('field3');
        $expected = [
            [
                'fieldName' => ['order' => 'asc']
            ],
            [
                'field2' => ['order' => 'desc']
            ],
            [
                'field3' => ['order' => 'asc']
            ]
        ];
        $actual = $this->queryBuilder->getRequest()->toArray();
        $this->assertSame($expected, $actual['sort']);
    }

    /**
     * @test
     */
    public function limitWorks()
    {
        $this->queryBuilder->limit(2);
        $actual = $this->queryBuilder->getRequest()->toArray();
        $this->assertSame(2, $actual['size']);
    }

    /**
     * @test
     */
    public function sortDescWorks()
    {
        $this->queryBuilder->sortDesc('fieldName');
        $expected = [
            [
                'fieldName' => ['order' => 'desc']
            ]
        ];
        $actual = $this->queryBuilder->getRequest()->toArray();
        $this->assertSame($expected, $actual['sort']);
    }

    /**
     * @return array
     */
    public function rangeConstraintExamples()
    {
        return [
            ['greaterThan', 'gt', 10],
            ['greaterThanOrEqual', 'gte', 20],
            ['lessThan', 'lt', 'now'],
            ['lessThanOrEqual', 'lte', 40]
        ];
    }

    /**
     * @test
     * @dataProvider rangeConstraintExamples
     */
    public function rangeConstraintsWork($constraint, $operator, $value)
    {
        $this->queryBuilder->$constraint('fieldName', $value);
        $expected = [
            'range' => [
                'fieldName' => [$operator => $value]
            ]
        ];
        $actual = $this->queryBuilder->getRequest()->toArray();
        $this->assertInArray($expected, $actual['query']['filtered']['filter']['bool']['must']);
    }

    /**
     * @return array
     */
    public function simpleAggregationExamples()
    {
        return [
            ['min', 'foo', 'bar', 10],
            ['terms', 'foo', 'bar', 10],
            ['sum', 'foo', 'bar', 10],
            ['stats', 'foo', 'bar', 10],
            ['value_count', 'foo', 'bar', 20]
        ];
    }

    /**
     * @test
     * @dataProvider simpleAggregationExamples
     *
     * @param string $type
     * @param string $name
     * @param string $field
     * @param integer size
     */
    public function anSimpleAggregationCanBeAddedToTheRequest($type, $name, $field, $size)
    {
        $expected = [
            $name => [
                $type => [
                    'field' => $field,
                    'size' => $size
                ]
            ]
        ];

        $this->queryBuilder->fieldBasedAggregation($name, $field, $type, '', $size);
        $actual = $this->queryBuilder->getRequest()->toArray();

        $this->assertInArray($expected, $actual);
    }

    /**
     * @test
     */
    public function anAggregationCanBeSubbedUnderAPath()
    {
        $this->queryBuilder->fieldBasedAggregation('foo', 'bar');
        $this->queryBuilder->fieldBasedAggregation('bar', 'bar', 'terms', 'foo');
        $this->queryBuilder->fieldBasedAggregation('baz', 'bar', 'terms', 'foo.bar');

        $expected = [
            'foo' => [
                'terms' => [
                    'field' => 'bar',
                    'size' => 10
                ],
                'aggregations' => [
                    'bar' => [
                        'terms' => [
                            'field' => 'bar',
                            'size' => 10
                        ],
                        'aggregations' => [
                            'baz' => [
                                'terms' => [
                                    'field' => 'bar',
                                    'size' => 10
                                ],
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $actual = $this->queryBuilder->getRequest()->toArray();
        $this->assertInArray($expected, $actual);
    }

    /**
     * @test
     * @expectedException \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\QueryBuildingException
     */
    public function ifTheParentPathDoesNotExistAnExceptionisThrown()
    {
        $this->queryBuilder->fieldBasedAggregation("foo", "bar");
        $this->queryBuilder->fieldBasedAggregation("bar", "bar", "terms", "doesNotExist");
    }

    /**
     * @test
     * @expectedException \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\QueryBuildingException
     */
    public function ifSubbedParentPathDoesNotExistAnExceptionisThrown()
    {
        $this->queryBuilder->fieldBasedAggregation("foo", "bar");
        $this->queryBuilder->fieldBasedAggregation("bar", "bar", "terms", "foo.doesNotExist");
    }

    /**
     * @test
     */
    public function aCustomAggregationDefinitionCanBeApplied()
    {
        $expected = [
            "foo" => [
                "some" => ["field" => "bar"],
                "custom" => ["field" => "bar"],
                "arrays" => ["field" => "bar"]
            ]
        ];

        $this->queryBuilder->aggregation("foo", $expected['foo']);
        $actual = $this->queryBuilder->getRequest()->toArray();

        $this->assertInArray($expected, $actual);
    }

    /**
     * @test
     */
    public function requestCanBeExtendedByArbitraryProperties()
    {
        $this->queryBuilder->request('foo.bar', ['field' => 'x']);
        $expected = [
            'bar' => ['field' => 'x']
        ];
        $actual = $this->queryBuilder->getRequest();
        $this->assertEquals($expected, $actual['foo']);
    }

    /**
     * @test
     */
    public function existingRequestPropertiesCanBeOverridden()
    {
        $this->queryBuilder->limit(2);
        $this->queryBuilder->request('limit', 10);
        $expected = 10;
        $actual = $this->queryBuilder->getRequest();
        $this->assertEquals($expected, $actual['limit']);
    }

    /**
     * @test
     */
    public function getTotalItemsReturnsZeroByDefault()
    {
        $this->assertSame(0, $this->queryBuilder->getTotalItems());
    }

    /**
     * @test
     */
    public function getTotalItemsReturnsTotalHitsIfItExists()
    {
        $this->inject($this->queryBuilder, 'result', ['hits' => ['total' => 123]]);
        $this->assertSame(123, $this->queryBuilder->getTotalItems());
    }

    /**
     * Test helper
     *
     * @param $expected
     * @param $actual
     * @return void
     */
    protected function assertInArray($expected, $actual)
    {
        foreach ($actual as $actualElement) {
            if ($actualElement === $expected) {
                $this->assertTrue(true);

                return;
            }
        }

        // because $expected !== $actual ALWAYS, this will NEVER match but display a nice error message.
        $this->assertSame($expected, $actual, 'The $expected array was not found inside $actual.');
    }
}
