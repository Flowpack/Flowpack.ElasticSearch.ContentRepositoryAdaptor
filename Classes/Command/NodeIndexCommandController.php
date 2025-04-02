<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Command;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\IndexDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\NodeTypeMappingBuilderInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ErrorHandling\ErrorHandlingService;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\ConfigurationException;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\RuntimeException;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\WorkspaceIndexer;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Flowpack\ElasticSearch\Transfer\Exception\ApiException;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\Workspace;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Cli\Exception\StopCommandException;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Booting\Exception\SubProcessException;
use Neos\Flow\Core\Booting\Scripts;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Utility\Files;
use Psr\Log\LoggerInterface;

/**
 * Provides CLI features for index handling
 *
 * @Flow\Scope("singleton")
 */
class NodeIndexCommandController extends CommandController
{
    /**
     * @Flow\InjectConfiguration(package="Neos.Flow")
     * @var array
     */
    protected $flowSettings;

    /**
     * @var array
     * @Flow\InjectConfiguration(package="Neos.ContentRepository.Search")
     */
    protected $settings;

    /**
     * @var bool
     * @Flow\InjectConfiguration(path="command.useSubProcesses")
     */
    protected $useSubProcesses = true;

    /**
     * @Flow\Inject
     * @var ErrorHandlingService
     */
    protected $errorHandlingService;

    /**
     * @Flow\Inject
     * @var NodeIndexer
     */
    protected $nodeIndexer;

    /**
     * @Flow\Inject
     * @var NodeTypeMappingBuilderInterface
     */
    protected $nodeTypeMappingBuilder;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\Inject
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @Flow\Inject
     * @var WorkspaceIndexer
     */
    protected $workspaceIndexer;

    /**
     * @Flow\Inject
     * @var ElasticSearchClient
     */
    protected $searchClient;

    /**
     * @var IndexDriverInterface
     * @Flow\Inject
     */
    protected $indexDriver;
    
    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    /**
     * Index a single node by the given identifier and workspace name
     *
     * @param string $identifier
     * @param string|null $workspace
     * @param string|null $postfix
     * @return void
     * @throws ApiException
     * @throws ConfigurationException
     * @throws Exception
     * @throws RuntimeException
     * @throws SubProcessException
     * @throws \Flowpack\ElasticSearch\Exception
     */
    public function indexNodeCommand(string $identifier, string $contentRepository = 'default', ?string $workspace = null, ?string $postfix = null): void
    {
        $nodeAggregateId = NodeAggregateId::fromString($identifier);
        $contentRepository = $this->contentRepositoryRegistry->get(ContentRepositoryId::fromString($contentRepository));
        $workspaceName = $workspace ? WorkspaceName::fromString($workspace) : null;

        if ($workspaceName === null && $this->settings['indexAllWorkspaces'] === false) {
            $workspaceName = WorkspaceName::forLive();
        }

        $updateAliases = false;
        if ($postfix !== null) {
            $this->nodeIndexer->setIndexNamePostfix($postfix);
        } elseif ($this->aliasesExist() === false) {
            $postfix = (string)time();
            $updateAliases = true;
            $this->nodeIndexer->setIndexNamePostfix($postfix);
        }

        $indexNode = function (NodeAggregateId $nodeAggregateId, WorkspaceName $workspaceName, DimensionSpacePoint $dimensionSpacePoint) use ($contentRepository) {
            $visibilityContraints = VisibilityConstraints::withoutRestrictions();
            $subgraph = $contentRepository->getContentGraph($workspaceName)->getSubgraph($dimensionSpacePoint, $visibilityContraints);

            $node = $subgraph->findNodeById($nodeAggregateId);

            if ($node === null) {
                return [$workspaceName->value, '-', json_encode($dimensionSpacePoint), 'not found'];
            }

            $this->nodeIndexer->setDimensions($dimensionSpacePoint);
            $this->nodeIndexer->indexNode($node);

            return [$workspaceName->value, $node->nodeTypeName->value, json_encode($dimensionSpacePoint), '<success>indexed</success>'];
        };

        $indexInWorkspace = function (ContentRepository $contentRepository, NodeAggregateId $nodeAggregateId, WorkspaceName $workspaceName) use ($indexNode) {

            $dimensionSpacePoints = $contentRepository->getVariationGraph()->getDimensionSpacePoints();

            $results = [];

            if ($dimensionSpacePoints->isEmpty()) {
                $results[] = $indexNode($nodeAggregateId, $workspaceName, DimensionSpacePoint::createWithoutDimensions());
            } else {
                foreach ($dimensionSpacePoints as $dimensionSpacePoint) {
                    $results[] = $indexNode($nodeAggregateId, $workspaceName, $dimensionSpacePoint);
                }
            }

            $this->output->outputTable($results, ['Workspace', 'NodeType', 'Dimensions', 'State']);
        };

        if ($workspaceName === null) {
            /** @var Workspace $iteratedWorkspace */
            foreach ($contentRepository->findWorkspaces() as $iteratedWorkspace) {
                $indexInWorkspace($contentRepository, $nodeAggregateId, $iteratedWorkspace->workspaceName);
            }
        } else {
            $workspaceInstance = $contentRepository->findWorkspaceByName($workspaceName);
            if ($workspaceInstance === null) {
                $this->outputLine('<error>Error: The given workspace (%s) does not exist.</error>', [$workspaceName->value]);
                $this->quit(1);
            }
            $indexInWorkspace($contentRepository, $nodeAggregateId, $workspaceName);
        }

        $this->nodeIndexer->flush();

        if ($updateAliases) {
            $dimensionSpacePoints = $contentRepository->getVariationGraph()->getDimensionSpacePoints();

            foreach ($dimensionSpacePoints as $dimensionSpacePoint) {
                $this->executeInternalCommand('aliasInternal', [
                    'dimensionSpacePoint' => $dimensionSpacePoint->toJson(),
                    'postfix' => $postfix,
                    'update' => false
                ]);
            }

            $this->nodeIndexer->updateMainAlias();
        }

        $this->outputErrorHandling();
    }

    /**
     * Index all nodes by creating a new index and when everything was completed, switch the index alias.
     *
     * This command (re-)indexes all nodes contained in the content repository and sets the schema beforehand.
     *
     * @param int|null $limit Amount of nodes to index at maximum
     * @param bool $update if TRUE, do not throw away the index at the start. Should *only be used for development*.
     * @param string|null $workspace name of the workspace which should be indexed
     * @param string|null $postfix Index postfix, index with the same postfix will be deleted if exist
     * @return void
     * @throws StopCommandException
     * @throws Exception
     * @throws ConfigurationException
     * @throws ApiException
     */
    public function buildCommand(?int $limit = null, bool $update = false, ?string $workspace = null, ?string $postfix = null): void
    {
        $contentRepositoryId = ContentRepositoryId::fromString('default');
        $workspaceName = $workspace ? WorkspaceName::fromString($workspace) : null;
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);

        $this->logger->info(sprintf('Starting elasticsearch indexing %s sub processes', $this->useSubProcesses ? 'with' : 'without'), LogEnvironment::fromMethodName(__METHOD__));

        if ($workspaceName !== null && $contentRepository->findWorkspaceByName($workspaceName) === null) {
            $this->logger->error('The given workspace (' . $workspaceName->value . ') does not exist.', LogEnvironment::fromMethodName(__METHOD__));
            $this->quit(1);
        }

        $postfix = (string)($postfix ?: time());
        $this->nodeIndexer->setIndexNamePostfix($postfix);

        $createIndicesAndApplyMapping = function (DimensionSpacePoint $dimensionSpacePoint) use ($update, $postfix) {
            $this->executeInternalCommand('createInternal', [
                'dimensionSpacePoint' => $dimensionSpacePoint->toJson(),
                'update' => $update,
                'postfix' => $postfix,
            ]);
        };

        $buildIndex = function (DimensionSpacePoint $dimensionSpacePoint) use ($contentRepository, $workspaceName, $limit, $postfix) {
            $this->build($contentRepository, $dimensionSpacePoint, $workspaceName, $postfix, $limit);
        };

        $refresh = function (DimensionSpacePoint $dimensionSpacePoint) use ($postfix) {
            $this->executeInternalCommand('refreshInternal', [
                'dimensionSpacePoint' => $dimensionSpacePoint->toJson(),
                'postfix' => $postfix,
            ]);
        };

        $updateAliases = function (DimensionSpacePoint $dimensionSpacePoint) use ($update, $postfix) {
            $this->executeInternalCommand('aliasInternal', [
                'dimensionSpacePoint' => $dimensionSpacePoint->toJson(),
                'postfix' => $postfix,
                'update' => $update,
            ]);
        };
        $contentRepository = $this->contentRepositoryRegistry->get(ContentRepositoryId::fromString('default'));
        $dimensionSpacePoints = $contentRepository->getVariationGraph()->getDimensionSpacePoints();

        $runAndLog = function ($command, string $stepInfo) use ($dimensionSpacePoints) {
            $timeStart = microtime(true);
            $this->output(str_pad($stepInfo . '... ', 20));
            array_map($command, iterator_to_array($dimensionSpacePoints));
            $this->outputLine('<success>Done</success> (took %s seconds)', [number_format(microtime(true) - $timeStart, 2)]);
        };

        $runAndLog($createIndicesAndApplyMapping, 'Creating indices and apply mapping');

        if ($this->aliasesExist() === false) {
            $runAndLog($updateAliases, 'Set up aliases');
        }

        $runAndLog($buildIndex, 'Indexing nodes');

        $runAndLog($refresh, 'Refresh indicies');
        $runAndLog($updateAliases, 'Update aliases');

        $this->outputLine('Update main alias');
        $this->nodeIndexer->updateMainAlias();

        $this->outputLine();
        $this->outputMemoryUsage();
    }

    /**
     * @return bool
     * @throws ApiException
     * @throws ConfigurationException
     * @throws Exception
     */
    private function aliasesExist(): bool
    {
        $aliasName = $this->searchClient->getIndexName();
        $aliasesExist = false;
        try {
            $aliasesExist = $this->indexDriver->getIndexNamesByAlias($aliasName) !== [];
        } catch (ApiException $exception) {
            // in case of 404, do not throw an error...
            if ($exception->getResponse()->getStatusCode() !== 404) {
                throw $exception;
            }
        }

        return $aliasesExist;
    }

    /**
     * Build up the node index
     *
     * @param ContentRepository $contentRepository
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @param WorkspaceName|null $workspaceName
     * @param string|null $postfix
     * @param int|null $limit
     * @throws ConfigurationException
     * @throws Exception
     * @throws RuntimeException
     * @throws SubProcessException
     */
    private function build(ContentRepository $contentRepository, DimensionSpacePoint $dimensionSpacePoint, ?WorkspaceName $workspaceName = null, ?string $postfix = null, ?int $limit = null): void
    {
        $this->configureNodeIndexer($dimensionSpacePoint, $postfix);

        $this->logger->info(vsprintf('Indexing %s nodes to %s', [($limit !== null ? 'the first ' . $limit . ' ' : ''), $this->nodeIndexer->getIndexName()]), LogEnvironment::fromMethodName(__METHOD__));

        if ($workspaceName === null && $this->settings['indexAllWorkspaces'] === false) {
            $workspaceName = WorkspaceName::forLive();
        }

        $buildWorkspaceCommandOptions = static function (ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName, DimensionSpacePoint $dimensionSpacePoint, ?int $limit, ?string $postfix) {
            return [
                'contentRepositoryId' => $contentRepositoryId->value,
                'workspaceName' => $workspaceName->value,
                'dimensionSpacePoint' => json_encode($dimensionSpacePoint),
                'postfix' => $postfix,
                'limit' => $limit,
            ];
        };

        $output = '';
        if ($workspaceName === null) {
            foreach ($contentRepository->findWorkspaces() as $iteratedWorkspace) {
                $output .= $this->executeInternalCommand('buildWorkspaceInternal', $buildWorkspaceCommandOptions($contentRepository->id, $iteratedWorkspace->workspaceName, $dimensionSpacePoint, $limit, $postfix));
            }
        } else {
            $output = $this->executeInternalCommand('buildWorkspaceInternal', $buildWorkspaceCommandOptions($contentRepository->id, $workspaceName, $dimensionSpacePoint, $limit, $postfix));
        }

        $outputArray = explode(PHP_EOL, $output);
        if (count($outputArray) > 0) {
            foreach ($outputArray as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $this->outputLine('<info>+</info> %s', [$line]);
            }
        }

        $this->outputErrorHandling();
    }

    /**
     * Internal sub-command to create an index and apply the mapping
     *
     * @param string $dimensionSpacePoint
     * @param bool $update
     * @param string|null $postfix
     * @throws ConfigurationException
     * @throws Exception
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws \Neos\Flow\Http\Exception
     * @Flow\Internal
     */
    public function createInternalCommand(string $dimensionSpacePoint, bool $update = false, ?string $postfix = null): void
    {
        $dimensionSpacePoint = DimensionSpacePoint::fromJsonString($dimensionSpacePoint);
        if ($update === true) {
            $this->logger->warning('!!! Update Mode (Development) active!', LogEnvironment::fromMethodName(__METHOD__));
        } else {
            $this->configureNodeIndexer($dimensionSpacePoint, $postfix);
            if ($this->nodeIndexer->getIndex()->exists() === true) {
                $this->logger->warning(sprintf('Deleted index with the same postfix (%s)!', $postfix), LogEnvironment::fromMethodName(__METHOD__));
                $this->nodeIndexer->getIndex()->delete();
            }
            $this->nodeIndexer->getIndex()->create();
            $this->logger->info('Created index ' . $this->nodeIndexer->getIndexName() . ' with dimensions ' . json_encode($dimensionSpacePoint), LogEnvironment::fromMethodName(__METHOD__));
        }

        $this->applyMapping();
        $this->outputErrorHandling();
    }

    /**
     * @param string $workspaceName
     * @param string $dimensionSpacePoint
     * @param string $postfix
     * @param int|null $limit
     * @return void
     * @Flow\Internal
     */
    public function buildWorkspaceInternalCommand(string $contentRepositoryId, string $workspaceName, string $dimensionSpacePoint, string $postfix, ?int $limit = null): void
    {
        $contentRepositoryId = ContentRepositoryId::fromString($contentRepositoryId);
        $workspaceName = WorkspaceName::fromString($workspaceName);
        $dimensionSpacePoint = DimensionSpacePoint::fromJsonString($dimensionSpacePoint);
        $this->configureNodeIndexer($dimensionSpacePoint, $postfix);

        $workspaceLogger = function (WorkspaceName $workspaceName, int $indexedNodes, DimensionSpacePoint $dimensionSpacePoint) use ($limit) {
            if ($dimensionSpacePoint->coordinates === []) {
                $message = 'Workspace "' . $workspaceName->value . '" without dimensions done. (Indexed ' . $indexedNodes . ' nodes)';
            } else {
                $message = 'Workspace "' . $workspaceName->value . '" and dimensions "' . $dimensionSpacePoint->toJson() . '" done. (Indexed ' . $indexedNodes . ' nodes)';
            }
            $this->outputLine($message);
        };

        $this->workspaceIndexer->indexWithDimensions($contentRepositoryId, $workspaceName, $dimensionSpacePoint, $limit, $workspaceLogger);

        $this->outputErrorHandling();
    }

    /**
     * Internal subcommand to refresh the index
     *
     * @param string $dimensionSpacePoint
     * @param string $postfix
     * @throws ConfigurationException
     * @throws Exception
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws \Neos\Flow\Http\Exception
     * @Flow\Internal
     */
    public function refreshInternalCommand(string $dimensionSpacePoint, string $postfix): void
    {
        $dimensionSpacePoint = DimensionSpacePoint::fromJsonString($dimensionSpacePoint);
        $this->configureNodeIndexer($dimensionSpacePoint, $postfix);

        $this->logger->info(vsprintf('Refreshing index %s', [$this->nodeIndexer->getIndexName()]), LogEnvironment::fromMethodName(__METHOD__));
        $this->nodeIndexer->getIndex()->refresh();

        $this->outputErrorHandling();
    }

    /**
     * @param string $dimensionSpacePoint
     * @param string $postfix
     * @param bool $update
     * @throws ApiException
     * @throws ConfigurationException
     * @throws Exception
     * @throws \Flowpack\ElasticSearch\Exception
     * @Flow\Internal
     */
    public function aliasInternalCommand(string $dimensionSpacePoint, string $postfix, bool $update = false): void
    {
        $dimensionSpacePoint = DimensionSpacePoint::fromJsonString($dimensionSpacePoint);
        if ($update === true) {
            return;
        }
        $this->configureNodeIndexer($dimensionSpacePoint, $postfix);

        $this->logger->info(vsprintf('Update alias for index %s', [$this->nodeIndexer->getIndexName()]), LogEnvironment::fromMethodName(__METHOD__));
        $this->nodeIndexer->updateIndexAlias();
        $this->outputErrorHandling();
    }

    /**
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @param string $postfix
     * @return DimensionSpacePoint
     */
    private function configureNodeIndexer(DimensionSpacePoint $dimensionSpacePoint, string $postfix): DimensionSpacePoint
    {
        $this->nodeIndexer->setIndexNamePostfix($postfix);
        $this->nodeIndexer->setDimensions($dimensionSpacePoint);
        return $dimensionSpacePoint;
    }

    /**
     * Clean up old indexes (i.e. all but the current one)
     *
     * @return void
     * @throws ConfigurationException
     * @throws Exception
     */
    public function cleanupCommand(): void
    {
        $removed = false;
        $contentRepository = $this->contentRepositoryRegistry->get(ContentRepositoryId::fromString('default'));
        $dimensionSpacePoints = $contentRepository->getVariationGraph()->getDimensionSpacePoints();
        foreach ($dimensionSpacePoints as $dimensionSpacePoint) {
            try {
                $this->nodeIndexer->setDimensions($dimensionSpacePoint);
                $removedIndices = $this->nodeIndexer->removeOldIndices();

                foreach ($removedIndices as $indexToBeRemoved) {
                    $removed = true;
                    $this->logger->info('Removing old index ' . $indexToBeRemoved, LogEnvironment::fromMethodName(__METHOD__));
                }
            } catch (ApiException $exception) {
                $exception->getResponse()->getBody()->rewind();
                $response = json_decode($exception->getResponse()->getBody()->getContents(), false);
                $message = sprintf('Nothing removed. ElasticSearch responded with status %s', $response->status);

                if (isset($response->error->type)) {
                    $this->logger->error(sprintf('%s, saying "%s: %s"', $message, $response->error->type, $response->error->reason), LogEnvironment::fromMethodName(__METHOD__));
                } else {
                    $this->logger->error(sprintf('%s, saying "%s"', $message, $response->error), LogEnvironment::fromMethodName(__METHOD__));
                }
            }
        }
        if ($removed === false) {
            $this->logger->info('Nothing to remove.', LogEnvironment::fromMethodName(__METHOD__));
        }
    }

    private function outputErrorHandling(): void
    {
        if ($this->errorHandlingService->hasError() === false) {
            return;
        }

        $this->outputLine();
        $this->outputLine('<error>%s Errors where returned while indexing. Check your logs for more information.</error>', [$this->errorHandlingService->getErrorCount()]);
    }

    /**
     * @param string $command
     * @param array $arguments
     * @return string
     * @throws RuntimeException
     * @throws SubProcessException
     */
    private function executeInternalCommand(string $command, array $arguments): string
    {
        ob_start(null, 1 << 20);

        if ($this->useSubProcesses) {
            $commandIdentifier = 'flowpack.elasticsearch.contentrepositoryadaptor:nodeindex:' . $command;
            $status = Scripts::executeCommand($commandIdentifier, $this->flowSettings, true, array_filter($arguments));

            if ($status !== true) {
                throw new RuntimeException(vsprintf('Command: %s with parameters: %s', [$commandIdentifier, json_encode($arguments)]), 1426767159);
            }
        } else {
            $commandIdentifier = $command . 'Command';
            call_user_func_array([self::class, $commandIdentifier], $arguments);
        }

        return ob_get_clean();
    }

    /**
     * Apply the mapping to the current index.
     *
     * @return void
     * @throws Exception
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws ConfigurationException
     * @throws \Neos\Flow\Http\Exception
     */
    private function applyMapping(string $contentRepository = 'default'): void
    {
        $contentRepositoryId = ContentRepositoryId::fromString($contentRepository);
        $nodeTypeMappingCollection = $this->nodeTypeMappingBuilder->buildMappingInformation($contentRepositoryId, $this->nodeIndexer->getIndex());
        foreach ($nodeTypeMappingCollection as $mapping) {
            /** @var Mapping $mapping */
            $mapping->apply();
        }
    }

    private function outputMemoryUsage(): void
    {
        $this->outputLine('! Memory usage %s', [Files::bytesToSizeString(memory_get_usage(true))]);
    }
}
