<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\ViewHelpers;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryResult;
use Neos\Flow\Annotations as Flow;
use Neos\FluidAdaptor\Core\ViewHelper\AbstractViewHelper;
use Neos\ContentRepository\Domain\Model\NodeInterface;

/**
 * View helper to get the raw "hits" array of an ElasticSearchQueryResult for a
 * specific node.
 *
 * = Examples =
 *
 * <code title="Basic usage">
 * {esCrAdapter:geHitArrayForNode(queryResultObject: result, node: node)}
 * </code>
 * <output>
 * array
 * </output>
 *
 * You can also return specific data
 * <code title="Fetch specific data">
 * {esCrAdapter:geHitArrayForNode(queryResultObject: result, node: node, path: 'sort')}
 * </code>
 * <output>
 * array or single value
 * </output>
 */
class GetHitArrayForNodeViewHelper extends AbstractViewHelper
{
    /**
     * @param ElasticSearchQueryResult $queryResultObject
     * @param NodeInterface $node
     * @param array|string $path
     * @return array
     */
    public function render(ElasticSearchQueryResult $queryResultObject, NodeInterface $node, $path = null)
    {
        $hitArray = $queryResultObject->searchHitForNode($node);

        if (!empty($path)) {
            return \Neos\Utility\Arrays::getValueByPath($hitArray, $path);
        }

        return $hitArray;
    }
}
