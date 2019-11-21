<?php
declare(strict_types=1);

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
 * Get the index name from settings
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

    /**
     * @return string
     * @throws Exception\ConfigurationException
     */
    public function get(): string
    {
        $name = $this->indexName;
        if ($name === '') {
            throw new Exception\ConfigurationException('Index name can not be null, check Settings at path: Neos.ContentRepository.Search.elasticSearch.indexName', 1574327388);
        }

        return $name;
    }
}
