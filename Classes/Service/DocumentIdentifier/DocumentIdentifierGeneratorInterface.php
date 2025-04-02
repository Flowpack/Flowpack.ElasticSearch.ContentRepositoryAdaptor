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
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;

interface DocumentIdentifierGeneratorInterface
{
    /**
     * Generates a stable identifier out of the given node
     *
     * @param Node $node
     * @param string|null $targetWorkspaceName
     * @return string
     */
    public function generate(Node $node, ?WorkspaceName $targetWorkspaceName = null): string;
}
