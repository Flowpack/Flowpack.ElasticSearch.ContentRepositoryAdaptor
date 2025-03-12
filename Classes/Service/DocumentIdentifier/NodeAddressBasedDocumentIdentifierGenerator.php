<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\DocumentIdentifier;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;

class NodeAddressBasedDocumentIdentifierGenerator implements DocumentIdentifierGeneratorInterface
{
    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    public function generate(Node $node, ?WorkspaceName $targetWorkspaceName = null): string
    {
        $nodeAddress = NodeAddress::create(
            $node->contentRepositoryId,
            $targetWorkspaceName ?: $node->workspaceName,
            $node->dimensionSpacePoint,
            $node->aggregateId
        );

        return sha1($nodeAddress->toJson());
    }
}
