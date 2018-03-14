<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service;

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
use Neos\Flow\Annotations as Flow;

/**
 * Get Index Name from Settings
 *
 * @Flow\Scope("singleton")
 */
class IndexNameStrategy implements IndexNameStrategyInterface
{
    /**
     * @var string
     * @Flow\InjectConfiguration(path="elasticSearch.indexName", package="Neos.ContentRepository.Search")
     */
    protected $indexName;

    public function get()
    {
        $name = $this->indexName;
        if ($name === '') {
            throw new Exception('Index name can not be null, check Settings at path: Neos.ContentRepository.Search.elasticSearch.indexName');
        }

        return $name;
    }
}
