<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Domain\Model;

/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch.ContentRepositoryAdaptor". *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;

class FilteredQuery extends AbstractQuery
{
    /**
     * The ElasticSearch request, as it is being built up.
     * @var array
     */
    protected $request = [
        'query' => [
            'filtered' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'match_all' => []
                            ]
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
                            ],
                        ],
                    ]
                ]
            ]
        ],
        'fields' => ['__path']
    ];

    /**
     * {@inheritdoc}
     */
    public function getCountRequestAsJSON()
    {
        $request = $this->request;
        foreach ($this->unsupportedFieldsInCountRequest as $field) {
            if (isset($request[$field])) {
                unset($request[$field]);
            }
        }
        return json_encode($request);
    }

    /**
     * {@inheritdoc}
     */
    public function size($size)
    {
        $this->request['size'] = (integer)$size;
        return $this->queryBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function from($size)
    {
        $this->request['from'] = (integer)$size;
        return $this->queryBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function fulltext($searchWord)
    {
        $this->appendAtPath('query.filtered.query.bool.must', [
            'query_string' => [
                'query' => $searchWord
            ]
        ]);
        return $this->queryBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function queryFilter($filterType, $filterOptions, $clauseType = 'must')
    {
        if (!in_array($clauseType, ['must', 'should', 'must_not'])) {
            throw new Exception\QueryBuildingException('The given clause type "' . $clauseType . '" is not supported. Must be one of "must", "should", "must_not".', 1383716082);
        }
        return $this->appendAtPath('query.filtered.filter.bool.' . $clauseType, [$filterType => $filterOptions]);
    }

}
