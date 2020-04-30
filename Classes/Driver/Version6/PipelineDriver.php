<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version6;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\AbstractDriver;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\PipelineDriverInterface;

/**
 * @Flow\Scope("singleton")
 */
class PipelineDriver extends AbstractDriver implements PipelineDriverInterface
{
    /**
     * @var array
     */
    protected $pipelineConfigurations;

    /**
     * @param array $pipelineConfigurations
     */
    public function __construct(array $pipelineConfigurations = [])
    {
        $this->pipelineConfigurations = $pipelineConfigurations;
    }

    /**
     * @throws \Flowpack\ElasticSearch\Transfer\Exception
     * @throws \Flowpack\ElasticSearch\Transfer\Exception\ApiException
     * @throws \Neos\Flow\Http\Exception
     */
    public function updatePipelines(): void
    {
        foreach ($this->pipelineConfigurations as $pipelineIdentifier => $pipelineDefinition) {
            if (($pipelineDefinition['enabled'] ?? false) === false) {
                continue;
            }

            if (!isset($pipelineDefinition['configuration']) || !is_array($pipelineDefinition['configuration'])) {
                continue;
            }

            $this->searchClient->request('PUT', sprintf('_ingest/pipeline/%s', $pipelineIdentifier), [], json_encode($pipelineDefinition['configuration']))->getTreatedContent();
        }
    }

    /**
     * @param string $pipelineIdentifier
     * @return bool
     * @throws \Flowpack\ElasticSearch\Transfer\Exception
     * @throws \Flowpack\ElasticSearch\Transfer\Exception\ApiException
     * @throws \Neos\Flow\Http\Exception
     */
    public function hasPipeLine(string $pipelineIdentifier): bool
    {
        $result = $this->searchClient->request('GET', sprintf('_ingest/pipeline/%s', $pipelineIdentifier))->getTreatedContent();
        return !empty($result);
    }

    /**
     * @param string $pipelineIdentifier
     * @throws \Flowpack\ElasticSearch\Transfer\Exception
     * @throws \Flowpack\ElasticSearch\Transfer\Exception\ApiException
     * @throws \Neos\Flow\Http\Exception
     */
    public function deletePipeLine(string $pipelineIdentifier): void
    {
        $this->searchClient->request('DELETE', sprintf('_ingest/pipeline/%s', $pipelineIdentifier));
    }
}
