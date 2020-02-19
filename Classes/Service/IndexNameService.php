<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

class IndexNameService
{

    public CONST INDEX_PART_SEPARATOR = '-';

    /**
     * @param array $indexNames
     * @param string $postfix
     * @return array
     */
    public static function filterIndexNamesByPostfix(array $indexNames, string $postfix): array
    {
        return array_values(array_filter($indexNames, static function ($indexName) use ($postfix) {
            $postfixWithSeparator = self::INDEX_PART_SEPARATOR . $postfix;
            return substr($indexName, -strlen($postfixWithSeparator)) === $postfixWithSeparator;
        }));
    }
}
