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
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\DriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Mapping\NodeTypeMappingBuilder;
use Flowpack\ElasticSearch\Domain\Model\Document as ElasticSearchDocument;
use Flowpack\ElasticSearch\Domain\Model\Index;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

/**
 * Fulltext Indexer Driver for Elastic version 1.x
 *
 * @Flow\Scope("singleton")
 */
class Driver extends AbstractDriver implements DriverInterface
{
	/**
	 * @Flow\Inject
	 * @var ElasticSearchClient
	 */
	protected $searchClient;

	/**
	 * @param array $indices
	 */
	public function deleteIndices(array $indices)
	{
		if (count($indices) === 0) {
			return;
		}
		$this->searchClient->request('DELETE', '/' . implode(',', $indices) . '/');
	}

	/**
	 * @return array
	 */
	public function status()
	{
		return $this->searchClient->request('GET', '/_status')->getTreatedContent();
	}

	/**
	 * @param Index $index
	 * @param string|array $request
	 * @return array
	 */
	public function bulk(Index $index, $request)
	{
		if (is_array($request)) {
			$request = json_encode($request);
		}

		$response = $index->request('POST', '/_bulk', [], $request)->getOriginalResponse()->getContent();

		return array_map(function ($line) {
			return json_decode($line);
		}, explode("\n", $response));
	}

	/**
	 * @param NodeInterface $node
	 * @param $identifier
	 * @return array
	 */
	public function delete(NodeInterface $node, $identifier)
	{
		return [
			[
				'delete' => [
					'_type' => NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($node->getNodeType()),
					'_id' => $identifier
				]
			]
		];
	}

	/**
	 * @param string $aliasName
	 * @return array
	 */
	public function currentlyLiveIndices($aliasName)
	{
		return array_keys($this->searchClient->request('GET', '/_alias/' . $aliasName)->getTreatedContent());
	}

	/**
	 * @param Index $index
	 * @param NodeInterface $node
	 * @param string $documentIdentifier
	 */
	public function deleteByDocumentIdentifier(Index $index, NodeInterface $node, $documentIdentifier)
	{
		$index->request('DELETE', '/_query', [], json_encode([
			'query' => [
				'bool' => [
					'must' => [
						'ids' => [
							'values' => [$documentIdentifier]
						]
					],
					'must_not' => [
						'term' => [
							'_type' => NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($node->getNodeType()->getName())
						]
					],
				]
			]
		]));
	}

	/**
	 * @param array $actions
	 */
	public function aliasActions(array $actions)
	{
		$this->searchClient->request('POST', '/_aliases', [], \json_encode(['actions' => $actions]));
	}

	/**
	 * @param string $aliasName
	 * @throws Exception
	 */
	public function removeAlias($aliasName)
	{
		$response = $this->searchClient->request('HEAD', '/' . $aliasName);
		if ($response->getStatusCode() === 200) {
			$response = $this->searchClient->request('DELETE', '/' . $aliasName);
			if ($response->getStatusCode() !== 200) {
				throw new Exception('The index "' . $aliasName . '" could not be removed to be replaced by an alias. (return code: ' . $response->getStatusCode() . ')', 1395419177);
			}
		}
	}

	/**
	 * @param string $aliasName
	 * @return array
	 * @throws Exception
	 */
	public function indexNames($aliasName)
	{
		$response = $this->searchClient->request('GET', '/_alias/' . $aliasName);
		if ($response->getStatusCode() !== 200) {
			throw new Exception('The alias "' . $aliasName . '" was not found with some unexpected error... (return code: ' . $response->getStatusCode() . ')', 1383650137);
		}

		return array_keys($response->getTreatedContent());
	}

	/**
	 * @param NodeInterface $node
	 * @param array $fulltextIndexOfNode
	 * @param string $targetWorkspaceName
	 * @return array
	 */
	public function fulltext(NodeInterface $node, array $fulltextIndexOfNode, $targetWorkspaceName = null)
	{
		$closestFulltextNode = $node;
		while (!$this->isFulltextRoot($closestFulltextNode)) {
			$closestFulltextNode = $closestFulltextNode->getParent();
			if ($closestFulltextNode === null) {
				// root of hierarchy, no fulltext root found anymore, abort silently...
				$this->logger->log(sprintf('NodeIndexer: No fulltext root found for node %s (%)', $node->getPath(), $node->getIdentifier()), LOG_WARNING, null, 'ElasticSearch (CR)');

				return null;
			}
		}

		$closestFulltextNodeContextPath = $closestFulltextNode->getContextPath();
		if ($targetWorkspaceName !== null) {
			$closestFulltextNodeContextPath = str_replace($node->getContext()->getWorkspace()->getName(), $targetWorkspaceName, $closestFulltextNodeContextPath);
		}
		$closestFulltextNodeDocumentIdentifier = sha1($closestFulltextNodeContextPath);

		if ($closestFulltextNode->isRemoved()) {
			// fulltext root is removed, abort silently...
			$this->logger->log(sprintf('NodeIndexer (%s): Fulltext root found for %s (%s) not updated, it is removed', $closestFulltextNodeDocumentIdentifier, $node->getPath(), $node->getIdentifier()), LOG_DEBUG, null, 'ElasticSearch (CR)');
			return null;
		}

		$this->logger->log(sprintf('NodeIndexer (%s): Updated fulltext index for %s (%s)', $closestFulltextNodeDocumentIdentifier, $closestFulltextNodeContextPath, $closestFulltextNode->getIdentifier()), LOG_DEBUG, null, 'ElasticSearch (CR)');

		return [
			[
				'update' => [
					'_type' => NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($closestFulltextNode->getNodeType()->getName()),
					'_id' => $closestFulltextNodeDocumentIdentifier
				]
			],
			// http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/docs-update.html
			[
				// first, update the __fulltextParts, then re-generate the __fulltext from all __fulltextParts
				'script' => '
                    if (!ctx._source.containsKey("__fulltextParts")) {
                        ctx._source.__fulltextParts = new LinkedHashMap();
                    }

                    if (nodeIsRemoved || nodeIsHidden || fulltext.size() == 0) {
                        if (ctx._source.__fulltextParts.containsKey(identifier)) {
                            ctx._source.__fulltextParts.remove(identifier);
                        }
                    } else {
                        ctx._source.__fulltextParts[identifier] = fulltext;
                    }
                    ctx._source.__fulltext = new LinkedHashMap();

                    Iterator<LinkedHashMap.Entry<String, LinkedHashMap>> fulltextByNode = ctx._source.__fulltextParts.entrySet().iterator();
                    for (fulltextByNode; fulltextByNode.hasNext();) {
                        Iterator<LinkedHashMap.Entry<String, String>> elementIterator = fulltextByNode.next().getValue().entrySet().iterator();
                        for (elementIterator; elementIterator.hasNext();) {
                            Map.Entry<String, String> element = elementIterator.next();
                            String value;

                            if (ctx._source.__fulltext.containsKey(element.key)) {
                                value = ctx._source.__fulltext[element.key] + " " + element.value.trim();
                            } else {
                                value = element.value.trim();
                            }

                            ctx._source.__fulltext[element.key] = value;
                        }
                    }
                ',
				'params' => [
					'identifier' => $node->getIdentifier(),
					'nodeIsRemoved' => $node->isRemoved(),
					'nodeIsHidden' => $node->isHidden(),
					'fulltext' => $fulltextIndexOfNode
				],
				'upsert' => [
					'__fulltext' => $fulltextIndexOfNode,
					'__fulltextParts' => [
						$node->getIdentifier() => $fulltextIndexOfNode
					]
				],
				'lang' => 'groovy'
			]
		];
	}
}
