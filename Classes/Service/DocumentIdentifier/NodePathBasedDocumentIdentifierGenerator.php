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

use Neos\ContentRepository\Domain\Model\NodeInterface;

class NodePathBasedDocumentIdentifierGenerator implements DocumentIdentifierGeneratorInterface
{
    public function generate(NodeInterface $node, ?string $targetWorkspaceName = null): string
    {
        $contextPath = $node->getContextPath();

        if ($targetWorkspaceName !== null) {
            $contextPath = str_replace($node->getContext()->getWorkspace()->getName(), $targetWorkspaceName, $contextPath);
        }

        return sha1($contextPath);
    }
}
