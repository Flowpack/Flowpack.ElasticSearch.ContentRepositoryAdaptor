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
	 * @param string $contextPathHash
	 */
	public function deleteByContextPathHash(Index $index, NodeInterface $node, $contextPathHash)
	{
		$index->request('DELETE', '/_query', [], json_encode([
			'query' => [
				'bool' => [
					'must' => [
						'ids' => [
							'values' => [$contextPathHash]
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
	 * @param ElasticSearchDocument $document
	 * @param array $documentData
	 * @return array
	 */
	public function fulltextRootNode(NodeInterface $node, ElasticSearchDocument $document, array $documentData)
	{
		if ($this->isFulltextRoot($node)) {
			// for fulltext root documents, we need to preserve the "__fulltext" field. That's why we use the
			// "update" API instead of the "index" API, with a custom script internally; as we
			// shall not delete the "__fulltext" part of the document if it has any.
			return [
				[
					'update' => [
						'_type' => $document->getType()->getName(),
						'_id' => $document->getId()
					]
				],
				// http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/docs-update.html
				[
					'script' => '
                            fulltext = (ctx._source.containsKey("__fulltext") ? ctx._source.__fulltext : new LinkedHashMap());
                            fulltextParts = (ctx._source.containsKey("__fulltextParts") ? ctx._source.__fulltextParts : new LinkedHashMap());
                            ctx._source = newData;
                            ctx._source.__fulltext = fulltext;
                            ctx._source.__fulltextParts = fulltextParts
                            ',
					'params' => [
						'newData' => $documentData
					],
					'upsert' => $documentData,
					'lang' => 'groovy'
				]
			];
		}

		// non-fulltext-root documents can be indexed as-they-are
		return [
			[
				'index' => [
					'_type' => $document->getType()->getName(),
					'_id' => $document->getId()
				]
			],
			$documentData
		];
	}

	/**
	 * @param NodeInterface $node
	 * @param array $fulltextIndexOfNode
	 * @param string $targetWorkspaceName
	 * @return array
	 */
	public function fulltext(NodeInterface $node, array $fulltextIndexOfNode, $targetWorkspaceName = null)
	{
		if ((($targetWorkspaceName !== null && $targetWorkspaceName !== 'live') || $node->getWorkspace()->getName() !== 'live') || count($fulltextIndexOfNode) === 0) {
			return [];
		}

		$closestFulltextNode = $node;
		while (!$this->isFulltextRoot($closestFulltextNode)) {
			$closestFulltextNode = $closestFulltextNode->getParent();
			if ($closestFulltextNode === null) {
				// root of hierarchy, no fulltext root found anymore, abort silently...
				$this->logger->log('No fulltext root found for ' . $node->getPath(), LOG_WARNING);

				return [];
			}
		}

		$closestFulltextNodeContextPath = str_replace($closestFulltextNode->getContext()->getWorkspace()->getName(), 'live', $closestFulltextNode->getContextPath());
		$closestFulltextNodeContextPathHash = sha1($closestFulltextNodeContextPath);

		return [
			[
				'update' => [
					'_type' => NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($closestFulltextNode->getNodeType()->getName()),
					'_id' => $closestFulltextNodeContextPathHash
				]
			],
			// http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/docs-update.html
			[
				// first, update the __fulltextParts, then re-generate the __fulltext from all __fulltextParts
				'script' => '
                    if (!ctx._source.containsKey("__fulltextParts")) {
                        ctx._source.__fulltextParts = new LinkedHashMap();
                    }
                    ctx._source.__fulltextParts[identifier] = fulltext;
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
