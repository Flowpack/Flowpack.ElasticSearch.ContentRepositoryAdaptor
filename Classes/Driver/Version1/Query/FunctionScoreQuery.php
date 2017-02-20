<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version1\Query;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use TYPO3\Flow\Utility\Arrays;

class FunctionScoreQuery extends FilteredQuery
{
    /**
     * @var array
     */
    protected $functionScoreRequest = [
        'functions' => []
    ];

    /**
     * @param array $functions
     * @return void
     */
    public function functions(array $functions)
    {
        if (isset($functions['functions'])) {
            $this->functionScoreRequest = $functions;
        } else {
            $this->functionScoreRequest['functions'] = $functions;
        }
    }

    /**
     * @param string $scoreMode
     * @return void
     * @throws Exception\QueryBuildingException
     */
    public function scoreMode($scoreMode)
    {
        if (!in_array($scoreMode, ['multiply', 'first', 'sum', 'avg', 'max', 'min'])) {
            throw new Exception\QueryBuildingException('Invalid score mode', 1454016230);
        }
        $this->functionScoreRequest['score_mode'] = $scoreMode;
    }

    /**
     * @param string $boostMode
     * @return void
     * @throws Exception\QueryBuildingException
     */
    public function boostMode($boostMode)
    {
        if (!in_array($boostMode, ['multiply', 'replace', 'sum', 'avg', 'max', 'min'])) {
            throw new Exception\QueryBuildingException('Invalid boost mode', 1454016229);
        }
        $this->functionScoreRequest['boost_mode'] = $boostMode;
    }

    /**
     * @param integer|float $boost
     * @return void
     * @throws Exception\QueryBuildingException
     */
    public function maxBoost($boost)
    {
        if (!is_numeric($boost)) {
            throw new Exception\QueryBuildingException('Invalid max boost', 1454016230);
        }
        $this->functionScoreRequest['max_boost'] = $boost;
    }

    /**
     * @param integer|float $score
     * @return void
     * @throws Exception\QueryBuildingException
     */
    public function minScore($score)
    {
        if (!is_numeric($score)) {
            throw new Exception\QueryBuildingException('Invalid max boost', 1454016230);
        }
        $this->functionScoreRequest['min_score'] = $score;
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareRequest()
    {
        if ($this->functionScoreRequest['functions'] === []) {
            return parent::prepareRequest();
        }
        $currentQuery = $this->request['query'];

        $baseQuery = $this->request;
        unset($baseQuery['query']);

        $functionScore = $this->functionScoreRequest;
        $functionScore['query'] = $currentQuery;
        $query = Arrays::arrayMergeRecursiveOverrule($baseQuery, [
            'query' => [
                'function_score' => $functionScore
            ]
        ]);

        return $query;
    }
}
