<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version2\Query;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version1;

/**
 * Default Filtered Query
 */
class FilteredQuery extends Version1\Query\FilteredQuery
{
    /**
     * @param $fragmentSize
     * @param null $fragmentCount
     */
    public function highlight($fragmentSize, $fragmentCount = null)
    {
        if ($fragmentSize === false) {
            // Highlighting is disabled.
            unset($this->request['highlight']);
        } else {
            $highlightQuery = $this->request['query'];
            $highlightQuery['filtered']['query']['bool']['must'][1]['query_string']['fields'] = ['__fulltext*'];

            $this->request['highlight'] = [
                'fields' => [
                    '__fulltext*' => [
                        'fragment_size' => $fragmentSize,
                        'no_match_size' => $fragmentSize,
                        'number_of_fragments' => $fragmentCount,
                        'highlight_query' => $highlightQuery
                    ]
                ]
            ];
        }
    }
}
