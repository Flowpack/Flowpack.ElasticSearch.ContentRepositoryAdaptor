<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Tests\Functional\Driver;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\PipelineDriverInterface;
use Neos\Flow\Tests\FunctionalTestCase;

class PipelineDriverTest extends FunctionalTestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     */
    public function updatePipelines(): void
    {
        $pipelineName = 'neos_contentrepository_test';

        /** @var PipelineDriverInterface $pipelineDriver */
        $pipelineDriver = $this->objectManager->get(PipelineDriverInterface::class);

        if ($pipelineDriver->hasPipeLine($pipelineName)) {
            $pipelineDriver->deletePipeLine($pipelineName);
        }

        $pipelineDriver->updatePipelines();

        self::assertTrue($pipelineDriver->hasPipeLine($pipelineName), sprintf('Pipeline with identidfier "%s" not found', $pipelineName));
        $pipelineDriver->deletePipeLine($pipelineName);
        self::assertFalse($pipelineDriver->hasPipeLine($pipelineName), sprintf('Pipeline "%s" should have been deleted', $pipelineName));
    }
}
