<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\AssetExtraction;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as FLow;
use Neos\ContentRepository\Search\AssetExtraction\AssetExtractorInterface;
use Neos\ContentRepository\Search\Dto\AssetContent;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Media\Domain\Model\AssetInterface;
use Neos\Utility\Arrays;
use Psr\Log\LoggerInterface;

/**
 * @Flow\Scope("singleton")
 */
class IngestAttachmentAssetExtractor implements AssetExtractorInterface
{
    /**
     * @Flow\Inject
     * @var ElasticSearchClient
     */
    protected $elasticsearchClient;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Takes an asset and extracts content and meta data.
     *
     * @param AssetInterface $asset
     * @return AssetContent
     * @throws \Flowpack\ElasticSearch\Transfer\Exception
     * @throws \Flowpack\ElasticSearch\Transfer\Exception\ApiException
     * @throws \Neos\Flow\Http\Exception
     */
    public function extract(AssetInterface $asset): AssetContent
    {
        $request = [
            'pipeline' => [
                'description' => 'Attachment Extraction',
                'processors' => [
                    [
                        'attachment' => [
                            'field' => 'neos_asset',
                            'indexed_chars' => 100000,
                            'ignore_missing' => true,
                        ]
                    ]
                ]
            ],
            'docs' => [
                [
                    '_source' => [
                        'neos_asset' => $this->getAssetContent($asset)
                    ]
                ]
            ]
        ];

        $result = $this->elasticsearchClient->request('POST', '_ingest/pipeline/_simulate', [], json_encode($request))->getTreatedContent();
        $extractedAsset = Arrays::getValueByPath($result, 'docs.0.doc._source.attachment');

        $this->logger->debug(sprintf('Extracted asset %s of type %s. Extracted %s characters of content', $asset->getResource()->getFilename(), $extractedAsset['content_type'], $extractedAsset['content_length']), LogEnvironment::fromMethodName(__METHOD__));

        return new AssetContent(
            $extractedAsset['content'] ?? '',
            $extractedAsset['title'] ?? '',
            $extractedAsset['name'] ?? '',
            $extractedAsset['author'] ?? '',
            $extractedAsset['keywords'] ?? '',
            $extractedAsset['date'] ?? '',
            $extractedAsset['content_type'] ?? '',
            $extractedAsset['content_length'] ?? '',
            $extractedAsset['language'] ?? ''
        );
    }

    /**
     * @param AssetInterface $asset
     * @return null|string
     */
    protected function getAssetContent(AssetInterface $asset): ?string
    {
        $stream = $asset->getResource()->getStream();
        stream_filter_append($stream, 'convert.base64-encode');
        $result = stream_get_contents($stream);
        return $result !== false ? $result : null;
    }
}
