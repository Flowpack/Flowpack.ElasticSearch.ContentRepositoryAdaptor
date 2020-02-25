<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Domain\Model;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;

/**
 * Calculate the target ContextPath
 */
class TargetContextPath
{
    /**
     * @var string
     */
    protected $contextPath = '';

    /**
     * @param NodeInterface $node
     * @param string $targetWorkspaceName
     * @param string $contextPath
     * @throws IllegalObjectTypeException
     */
    public function __construct(NodeInterface $node, string $targetWorkspaceName, string $contextPath)
    {
        $this->contextPath = str_replace($node->getContext()->getWorkspace()->getName(), $targetWorkspaceName, $contextPath);
    }

    public function __toString()
    {
        return (string)$this->contextPath;
    }
}
