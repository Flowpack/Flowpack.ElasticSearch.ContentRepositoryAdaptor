<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\ViewHelpers;

/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch.ContentRepositoryAdaptor". *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel\ElasticSearchQueryResult;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Fluid\Core\ViewHelper\AbstractViewHelper;

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
    public function render(ElasticSearchQueryResult $queryResultObject, NodeInterface $node, $path = NULL)
    {
        $hitArray = $queryResultObject->searchHitForNode($node);

        if (!empty($path)) {
            return \TYPO3\Flow\Utility\Arrays::getValueByPath($hitArray, $path);
        }

        return $hitArray;
    }
}
