<?php

declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\IndexNameStrategyInterface;
use Flowpack\ElasticSearch\Domain\Model\Client;
use Flowpack\ElasticSearch\Domain\Model\Index;
use Neos\Flow\Annotations as Flow;

/**
 * The elasticsearch client to be used by the content repository adapter. Singleton, can be injected.
 *
 * Used to:
 *
 * - make the ElasticSearch Client globally available
 * - allow to access the index to be used for reading/writing in a global way
 *
 * @Flow\Scope("singleton")
 */
class ElasticSearchClient extends Client
{
    /**
     * @var IndexNameStrategyInterface
     * @Flow\Inject
     */
    protected $indexNameStrategy;

    /**
     * Get the index name to be used
     *
     * @return string
     * @throws Exception
     */
    public function getIndexName(): string
    {
        $name = trim($this->indexNameStrategy->get());
        if ($name === '') {
            throw new Exception('Index name can not be null');
        }

        return $name;
    }

    /**
     * Retrieve the index to be used for querying or on-the-fly indexing.
     * In Elasticsearch, this index is an *alias* to the currently used index.
     *
     * @return Index
     * @throws Exception
     */
    public function getIndex(): Index
    {
        return $this->findIndex($this->getIndexName());
    }
}
