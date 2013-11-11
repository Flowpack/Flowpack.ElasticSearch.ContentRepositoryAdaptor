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

/**
 * FulltextHelper (part of ElasticSearchHelper)
 *
 * @Flow\Scope("singleton")
 */
class FulltextHelper implements ProtectedContextAwareInterface {

	/**
	 *
	 * @param $string
	 * @return array
	 */
	public function extractHtmlTags($string) {
		// prevents concatenated words when stripping tags afterwards
		$string = str_replace(array('<', '>'), array(' <', '> '), $string);
		// strip all tags except h1-6
		$string = strip_tags($string, '<h1><h2><h3><h4><h5><h6>');

		$parts = array(
			'text' => ''
		);
		while (strlen($string) > 0) {

			$matches = array();
			if (preg_match('/<(h1|h2|h3|h4|h5|h6)[^>]*>.*?<\/\1>/ui', $string, $matches, PREG_OFFSET_CAPTURE)) {
				$fullMatch = $matches[0][0];
				$startOfMatch = $matches[0][1];
				$tagName = $matches[1][0];

				if ($startOfMatch > 0) {
					$parts['text'] .= substr($string, 0, $startOfMatch);
					$string = substr($string, $startOfMatch);
				}
				if (!isset($parts[$tagName])) {
					$parts[$tagName] = '';
				}

				$parts[$tagName] .= ' ' . $fullMatch;
				$string = substr($string, strlen($fullMatch));
			} else {
				// no h* found anymore in the remaining string
				$parts['text'] .= $string;
				break;
			}
		}


		foreach ($parts as &$part) {
			$part = preg_replace('/\s+/u', ' ', strip_tags($part));
		}

		return $parts;
	}

	/**
	 *
	 *
	 * @param $bucketName
	 * @param $string
	 * @return array
	 */
	public function extractInto($bucketName, $string) {
		return array(
			$bucketName => $string
		);
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