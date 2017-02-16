<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version1;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\AbstractDriver;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\SystemDriverInterface;
use TYPO3\Flow\Annotations as Flow;

/**
 * System driver for Elasticsearch version 1.x
 *
 * @Flow\Scope("singleton")
 */
class SystemDriver extends AbstractDriver implements SystemDriverInterface
{
    /**
     * {@inheritdoc}
     */
    public function status()
    {
        return $this->searchClient->request('GET', '/_status')->getTreatedContent();
    }
}
