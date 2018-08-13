<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Dto;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

class SearchResult
{
    /**
     * @var int
     */
    protected $total;

    /**
     * @var array
     */
    protected $hits;

    public function __construct(array $hits, int $total)
    {
        $this->hits = $hits;
        $this->total = $total;
    }

    /**
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @return array
     */
    public function getHits(): array
    {
        return $this->hits;
    }
}
