<?php

declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Eel\ProtectedContextAwareInterface;

/**
 * Eel Helper to process the ElasticSearch Query Result
 */
class SearchResultHelper implements ProtectedContextAwareInterface
{

    /**
     * The "didYouMean" operation processes the previously defined suggestion by extracting
     * and concatenating the best guess for each word given in the search term.
     * A threshold can be given as second parameter. If none of the suggestions reaches this
     * threshold, an empty string is returned.
     *
     * Example::
     *
     *     searchQuery = ${Search.query(site).fulltext('noes cms').termSuggestions('noes cms')}
     *     didYouMean = ${SearchResult.didYouMean(this.searchQuery.execute(), 0.5)}
     *
     * @param ElasticSearchQueryResult $searchResult The result of an elastic search query
     * @param float $scoreThreshold The minimum required score to return the suggestion
     * @param string $suggestionName The suggestion name which was given in the suggestion definition
     * @return string
     */
    public function didYouMean(ElasticSearchQueryResult $searchResult, float $scoreThreshold = 0.7, string $suggestionName = 'suggestions'): string
    {
        $maxScore = 0;
        $suggestionParts = [];

        foreach ($searchResult->getSuggestions()[$suggestionName] as $suggestion) {
            if (array_key_exists('options', $suggestion) && !empty($suggestion['options'])) {
                $bestSuggestion = current($suggestion['options']);
                $maxScore = $bestSuggestion['score'] > $maxScore ? $bestSuggestion['score'] : $maxScore;
                $suggestionParts[] = $bestSuggestion['text'];
            } else {
                $suggestionParts[] = $suggestion['text'];
            }
        }
        if ($maxScore >= $scoreThreshold) {
            return implode(' ', $suggestionParts);
        }

        return '';
    }

    /**
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
