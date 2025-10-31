<?php

declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Model\NodeInterface;

/**
 * Elasticsearch Document Driver Interface
 */
interface DocumentDriverInterface
{
    /**
     * Generate the query to delete Elastic document for the give node
     *
     * @param NodeInterface $node
     * @param string $identifier
     * @return array
     */
    public function delete(NodeInterface $node, string $identifier): array;
}
