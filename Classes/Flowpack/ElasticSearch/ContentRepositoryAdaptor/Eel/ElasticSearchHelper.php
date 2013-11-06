<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel;


/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch.ContentRepositoryAdaptor". *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use TYPO3\Eel\ProtectedContextAwareInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Model\NodeType;

/**
 * ElasticSearchHelper
 */
class ElasticSearchHelper implements ProtectedContextAwareInterface {

	/**
	 * Create a new ElasticSearch query underneath the given $node
	 *
	 * @param NodeInterface $node
	 * @return ElasticSearchQueryBuilder
	 */
	public function query(NodeInterface $node) {
		return new ElasticSearchQueryBuilder($node);
	}

	/**
	 * Build all path prefixes. From an input such as:
	 *
	 *   foo/bar/baz
	 *
	 * it emits an array with:
	 *
	 *   foo
	 *   foo/bar
	 *   foo/bar/baz
	 *
	 * This method works both with absolute and relative paths.
	 *
	 * @param string $path
	 * @return array<string>
	 */
	public function buildAllPathPrefixes($path) {
		if (strlen($path) === 0) {
			return array();
		} elseif ($path === '/') {
			return array('/');
		}

		$currentPath = '';
		if ($path{0} === '/') {
			$currentPath = '/';
		}
		$path = ltrim($path, '/');

		$pathPrefixes = array();
		foreach (explode('/', $path) as $pathPart) {
			$currentPath .= $pathPart . '/';
			$pathPrefixes[] = rtrim($currentPath, '/');
		}

		return $pathPrefixes;
	}

	/**
	 * Returns an array of node type names including the passed $nodeType and all its supertypes, recursively
	 *
	 * @param NodeType $nodeType
	 * @return array<String>
	 */
	public function extractNodeTypeNamesAndSupertypes(NodeType $nodeType) {
		$nodeTypeNames = array();
		$this->extractNodeTypeNamesAndSupertypesInternal($nodeType, $nodeTypeNames);
		return array_values($nodeTypeNames);
	}

	/**
	 * Recursive function for fetching all node type names
	 *
	 * @param NodeType $nodeType
	 * @param array $nodeTypeNames
	 * @return void
	 */
	protected function extractNodeTypeNamesAndSupertypesInternal(NodeType $nodeType, array &$nodeTypeNames) {
		$nodeTypeNames[$nodeType->getName()] = $nodeType->getName();
		foreach ($nodeType->getDeclaredSuperTypes() as $superType) {
			$this->extractNodeTypeNamesAndSupertypesInternal($superType, $nodeTypeNames);
		}
	}

	/**
	 * Convert an array of nodes to an array of node identifiers
	 *
	 * @param array<NodeInterface> $nodes
	 * @return array
	 */
	public function convertArrayOfNodesToArrayOfNodeIdentifiers($nodes) {
		if (!is_array($nodes) && !$nodes instanceof \Traversable) {
			return array();
		}
		$nodeIdentifiers = array();
		foreach ($nodes as $node) {
			$nodeIdentifiers[] = $node->getIdentifier();
		}

		return $nodeIdentifiers;
	}

	/**
	 * All methods are considered safe
	 *
	 * @param string $methodName
	 * @return boolean
	 */
	public function allowsCallOfMethod($methodName) {
		return TRUE;
	}
}