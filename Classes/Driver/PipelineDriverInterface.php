<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

interface PipelineDriverInterface
{
    /**
     * Creates or updates the configured ingest pipelines
     */
    public function updatePipelines(): void;

    /**
     * Checks if a given pipeline exists
     *
     * @param string $pipelineIdentifier
     * @return bool
     */
    public function hasPipeLine(string $pipelineIdentifier): bool;

    /**
     * Delete a pipeline
     *
     * @param string $pipelineIdentifier
     */
    public function deletePipeLine(string $pipelineIdentifier): void;
}
