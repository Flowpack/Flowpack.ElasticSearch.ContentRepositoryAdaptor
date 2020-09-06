<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional\Traits;

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
use Flowpack\ElasticSearch\Transfer\Exception\ApiException;
use Neos\ContentRepository\Domain\Model\NodeInterface;

trait Assertions
{

    /**
     * @param string $indexName
     * @throws \Flowpack\ElasticSearch\Transfer\Exception
     * @throws ApiException
     * @throws \Neos\Flow\Http\Exception
     */
    protected function assertIndexExists(string $indexName): void
    {
        $response = $this->searchClient->request('HEAD', '/' . $indexName);
        self::assertEquals(200, $response->getStatusCode());
    }

    protected function assertAliasesEquals(string $aliasPrefix, array $expectdAliases): void
    {
        $content = $this->searchClient->request('GET', '/_alias/' . $aliasPrefix . '*')->getTreatedContent();
        static::assertEquals($expectdAliases, array_keys($content));
    }

    private static function extractNodeNames(ElasticSearchQueryResult $result): array
    {
        return array_map(static function (NodeInterface $node) {
            return $node->getName();
        }, $result->toArray());
    }

    private static function assertNodeNames(array $expectedNames, ElasticSearchQueryResult $actualResult): void
    {
        sort($expectedNames);

        $actualNames = self::extractNodeNames($actualResult);
        sort($actualNames);

        self::assertEquals($expectedNames, $actualNames);
    }
}
