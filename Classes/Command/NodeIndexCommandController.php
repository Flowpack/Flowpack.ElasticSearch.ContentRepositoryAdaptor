<?php
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

use Doctrine\Common\Collections\ArrayCollection;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\NodeTypeMappingBuilderInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\Error\ErrorInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\WorkspaceIndexer;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\LoggerInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\DimensionsService;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\ErrorHandlingService;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Flowpack\ElasticSearch\Transfer\Exception\ApiException;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Domain\Service\ContentDimensionCombinator;
use Neos\ContentRepository\Domain\Service\ContentDimensionPresetSourceInterface;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Search\Indexer\NodeIndexerInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException;
use Neos\Flow\Core\Booting\Scripts;
use Neos\Flow\Exception;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Neos\Controller\CreateContentContextTrait;
use Neos\Utility\Files;
use Symfony\Component\Yaml\Yaml;

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

    use CreateContentContextTrait;

    /**
     * @Flow\Inject
     * @var ErrorHandlingService
     */
    protected $errorHandlingService;

    /**
     * @Flow\Inject
     * @var NodeIndexerInterface
     */
    protected $nodeIndexer;

    /**
     * @Flow\Inject
     * @var WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @Flow\Inject
     * @var NodeDataRepository
     */
    protected $nodeDataRepository;

    /**
     * @Flow\Inject
     * @var ContentDimensionPresetSourceInterface
     */
    protected $contentDimensionPresetSource;

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
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var DimensionsService
     */
    protected $dimensionsService;

    /**
     * @var ContentDimensionCombinator
     * @Flow\Inject
     */
    protected $contentDimensionCombinator;

    /**
     * @var WorkspaceIndexer
     * @Flow\Inject
     */
    protected $worksaceIndexer;

    /**
     * @var array
     */
    protected $settings;

    /**
     * Called by the Flow object framework after creating the object and resolving all dependencies.
     *
     * @param integer $cause Creation cause
     * @throws InvalidConfigurationTypeException
     */
    public function initializeObject($cause)
    {
        if ($cause === ObjectManagerInterface::INITIALIZATIONCAUSE_CREATED) {
            $this->settings = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.ContentRepository.Search');
        }
    }

    /**
     * Mapping between dimensions presets and index name
     */
    public function showDimensionsMappingCommand()
    {
        $indexName = $this->nodeIndexer->getIndexName();
        foreach ($this->contentDimensionCombinator->getAllAllowedCombinations() as $dimensionValues) {
            $this->outputLine('<info>%s-%s</info> %s', [$indexName, $this->dimensionsService->hash($dimensionValues), \json_encode($dimensionValues)]);
        }
    }

    /**
     * Show the mapping which would be sent to the ElasticSearch server
     *
     * @return void
     */
    public function showMappingCommand()
    {
        $nodeTypeMappingCollection = $this->nodeTypeMappingBuilder->buildMappingInformation($this->nodeIndexer->getIndex());
        foreach ($nodeTypeMappingCollection as $mapping) {
            /** @var Mapping $mapping */
            $this->output(Yaml::dump($mapping->asArray(), 5, 2));
            $this->outputLine();
        }
        $this->outputLine('------------');

        $mappingErrors = $this->nodeTypeMappingBuilder->getLastMappingErrors();
        if ($mappingErrors->hasErrors()) {
            $this->outputLine('<b>Mapping Errors</b>');
            foreach ($mappingErrors->getFlattenedErrors() as $errors) {
                foreach ($errors as $error) {
                    $this->outputLine($error);
                }
            }
        }

        if ($mappingErrors->hasWarnings()) {
            $this->outputLine('<b>Mapping Warnings</b>');
            foreach ($mappingErrors->getFlattenedWarnings() as $warnings) {
                foreach ($warnings as $warning) {
                    $this->outputLine($warning);
                }
            }
        }
    }

    /**
     * Index a single node by the given identifier and workspace name
     *
     * @param string $identifier
     * @param string $workspace
     * @param int $workspace
     * @return void
     * @throws StopActionException
     */
    public function indexNodeCommand($identifier, $workspace = null, $postfix = null)
    {
        if ($workspace === null && $this->settings['indexAllWorkspaces'] === false) {
            $workspace = 'live';
        }

        if ($postfix !== null) {
            $this->nodeIndexer->setIndexNamePostfix($postfix);
        }

        $indexNode = function ($identifier, Workspace $workspace, array $dimensions) {
            $context = $this->createContentContext($workspace->getName(), $dimensions);
            $node = $context->getNodeByIdentifier($identifier);
            if ($node === null) {
                $this->outputLine('Node with the given identifier is not found.');
                $this->quit();
            }
            $this->outputLine();
            $this->outputLine('Index node "%s" (%s)', [
                $node->getLabel(),
                $node->getIdentifier(),
            ]);
            $this->outputLine('  workspace: %s', [
                $workspace->getName()
            ]);
            $this->outputLine('  node type: %s', [
                $node->getNodeType()->getName()
            ]);
            $this->outputLine('  dimensions: %s', [
                json_encode($dimensions)
            ]);
            $this->nodeIndexer->indexNode($node);
        };

        $indexInWorkspace = function ($identifier, Workspace $workspace) use ($indexNode) {
            $combinations = $this->contentDimensionCombinator->getAllAllowedCombinations();
            if ($combinations === []) {
                $indexNode($identifier, $workspace, []);
            } else {
                foreach ($combinations as $combination) {
                    $indexNode($identifier, $workspace, $combination);
                }
            }
        };

        if ($workspace === null) {
            foreach ($this->workspaceRepository->findAll() as $workspace) {
                $indexInWorkspace($identifier, $workspace);
            }
        } else {
            /** @var Workspace $workspaceInstance */
            $workspaceInstance = $this->workspaceRepository->findByIdentifier($workspace);
            if ($workspaceInstance === null) {
                $this->outputLine('The given workspace (%s) does not exist.', [$workspace]);
                $this->quit(1);
            }
            $indexInWorkspace($identifier, $workspaceInstance);
        }
    }

    /**
     * Index all nodes by creating a new index and when everything was completed, switch the index alias.
     *
     * This command (re-)indexes all nodes contained in the content repository and sets the schema beforehand.
     *
     * @param integer $limit Amount of nodes to index at maximum
     * @param boolean $update if TRUE, do not throw away the index at the start. Should *only be used for development*.
     * @param string $workspace name of the workspace which should be indexed
     * @param string $postfix Index postfix, index with the same postfix will be deleted if exist
     * @return void
     * @throws StopActionException
     */
    public function buildCommand($limit = null, $update = false, $workspace = null, $postfix = '')
    {
        if ($workspace !== null && $this->workspaceRepository->findByIdentifier($workspace) === null) {
            $this->logger->log('The given workspace (' . $workspace . ') does not exist.', LOG_ERR);
            $this->quit(1);
        }

        $postfix = $postfix ?: time();
        $this->nodeIndexer->setIndexNamePostfix($postfix);

        $create = function (array $dimensionsValues) use ($update, $postfix) {
            $this->executeInternalCommand('createinternal', \array_filter([
                'postfix' => $postfix,
                'update' => $update,
                'dimensionsValues' => \json_encode($dimensionsValues)
            ]));
        };

        $build = function (array $dimensionsValues) use ($workspace, $limit, $update, $postfix) {
            $this->build($dimensionsValues, $workspace, $postfix, $limit);
        };

        $refresh = function (array $dimensionsValues) use ($postfix) {
            $this->executeInternalCommand('refreshinternal', \array_filter([
                'dimensionsValues' => \json_encode($dimensionsValues),
                'postfix' => $postfix,
            ]));
        };

        $updateAliases = function (array $dimensionsValues) use ($update, $postfix) {
            $this->executeInternalCommand('aliasinternal', \array_filter([
                'dimensionsValues' => \json_encode($dimensionsValues),
                'update' => $update,
                'postfix' => $postfix,
            ]));
        };

        $combinations = new ArrayCollection($this->contentDimensionCombinator->getAllAllowedCombinations());

        $this->outputSection('Create indicies ...');
        $combinations->map($create);

        $this->outputSection('Indexing nodes ...');
        $combinations->map($build);

        $this->outputSection('Refresh indicies ...');
        $combinations->map($refresh);

        $this->outputSection('Update aliases ...');
        $combinations->map($updateAliases);

        $this->nodeIndexer->updateMainAlias();

        $this->outputLine();
        $this->outputMemoryUsage();
    }

    /**
     * @param string $title
     */
    protected function outputSection($title)
    {
        $this->outputLine();
        $this->outputLine('<b>%s</b>', [$title]);
    }

    /**
     * @param string $dimensionsValues
     * @param bool $update
     * @param int $postfix
     * @Flow\Internal
     */
    public function createInternalCommand($dimensionsValues, $update = false, $postfix = null)
    {
        if ($update === true) {
            $this->logger->log('!!! Update Mode (Development) active!', LOG_INFO);
        } else {
            $dimensionsValues = $this->configureInternalCommand($dimensionsValues, $postfix);
            if ($this->nodeIndexer->getIndex()->exists() === true) {
                $this->logger->log(sprintf('Deleted index with the same postfix (%s)!', $postfix), LOG_WARNING);
                $this->nodeIndexer->getIndex()->delete();
            }
            $this->nodeIndexer->getIndex()->create();
            $this->logger->log('Created index ' . $this->nodeIndexer->getIndexName(), LOG_INFO);
            $this->logger->log('+ Dimensions: ' . \json_encode($dimensionsValues), LOG_INFO);
        }

        $this->applyMapping();
        $this->outputErrorHandling();
        $this->outputMemoryUsage();
    }

    /**
     * @param array $dimensionsValues
     * @param string $postfix
     * @param string $workspace
     * @param int $limit
     * @Flow\Internal
     * @throws Exception
     */
    public function build(array $dimensionsValues, $workspace = null, $postfix = null, $limit = null)
    {
        $dimensionsValues = $this->configureInternalCommand($dimensionsValues, $postfix);

        $this->logger->log(vsprintf('Indexing %snodes to %s', [($limit !== null ? 'the first ' . $limit . ' ' : ''), $this->nodeIndexer->getIndexName()]), LOG_INFO);

        if ($workspace === null && $this->settings['indexAllWorkspaces'] === false) {
            $workspace = 'live';
        }

        $buildWorkspaceCommandOptions = function ($workspace = null, array $dimensionsValues, $limit, $postfix) {
            return \array_filter([
                'workspace' => $workspace instanceof Workspace ? $workspace->getName() : $workspace,
                'dimensionsValues' => \json_encode($dimensionsValues),
                'limit' => $limit,
                'postfix' => $postfix
            ]);
        };

        $count = 0;
        if ($workspace === null) {
            foreach ($this->workspaceRepository->findAll() as $workspace) {
                $count += $this->executeBuildWorkspaceCommand($buildWorkspaceCommandOptions($workspace, $dimensionsValues, $limit, $postfix));
            }
        } else {
            $count += $this->executeBuildWorkspaceCommand($buildWorkspaceCommandOptions($workspace, $dimensionsValues, $limit, $postfix));
        }

        $this->outputErrorHandling();
        $this->logger->log('Done. (indexed ' . $count . ' nodes)', LOG_INFO);
    }

    /**
     * @param string $workspace
     * @param string $dimensionsValues
     * @param int $postfix
     * @param int $limit
     * @return int
     * @Flow\Internal
     */
    public function buildWorkspaceInternalCommand($workspace, $dimensionsValues, $postfix, $limit = null)
    {
        $dimensionsValues = $this->configureInternalCommand($dimensionsValues, $postfix);

        $workspaceLogger = function ($workspaceName, $indexedNodes, $dimensions) {
            if ($dimensions === []) {
                $message = 'Workspace "' . $workspaceName . '" without dimensions done. (Indexed ' . $indexedNodes . ' nodes)';
            } else {
                $message = 'Workspace "' . $workspaceName . '" and dimensions "' . json_encode($dimensions) . '" done. (Indexed ' . $indexedNodes . ' nodes)';
            }
            $this->outputLine($message);
        };

        $count = $this->worksaceIndexer->indexWithDimensions($workspace, $dimensionsValues, $limit, $workspaceLogger);

        $this->outputMemoryUsage();
        $this->outputErrorHandling();
        $this->outputLine($count);
    }

    /**
     * @param string $dimensionsValues
     * @param int $postfix
     * @Flow\Internal
     */
    public function refreshInternalCommand($dimensionsValues, $postfix)
    {
        $this->configureInternalCommand($dimensionsValues, $postfix);

        $this->logger->log(vsprintf('Refresh index %s', [$this->nodeIndexer->getIndexName()]), LOG_INFO);
        $this->outputMemoryUsage();

        $this->nodeIndexer->getIndex()->refresh();

        $this->outputErrorHandling();
    }

    /**
     * @param string $dimensionsValues
     * @param int $postfix
     * @param bool $update
     * @Flow\Internal
     */
    public function aliasInternalCommand($dimensionsValues, $postfix, $update = false)
    {
        if ($update === true) {
            return;
        }
        $this->configureInternalCommand($dimensionsValues, $postfix);

        $this->logger->log(vsprintf('Update alias for index %s', [$this->nodeIndexer->getIndexName()]), LOG_INFO);
        $this->nodeIndexer->updateIndexAlias();
        $this->outputErrorHandling();
    }

    /**
     * @param string|array $dimensionsValues
     * @param string $postfix
     * @return array
     */
    public function configureInternalCommand($dimensionsValues, $postfix)
    {
        if (!\is_array($dimensionsValues)) {
            $dimensionsValues = \json_decode($dimensionsValues, true);
        }

        $this->nodeIndexer->setIndexNamePostfix($postfix);
        $this->nodeIndexer->setDimensions($dimensionsValues);
        return $dimensionsValues;
    }

    /**
     * Clean up old indexes (i.e. all but the current one)
     *
     * @return void
     */
    public function cleanupCommand()
    {
        $removed = false;
        $combinations = $this->contentDimensionCombinator->getAllAllowedCombinations();
        foreach ($combinations as $dimensionsValues) {
            try {
                $this->nodeIndexer->setDimensions($dimensionsValues);
                $indicesToBeRemoved = $this->nodeIndexer->removeOldIndices();
                if (count($indicesToBeRemoved) > 0) {
                    foreach ($indicesToBeRemoved as $indexToBeRemoved) {
                        $removed = true;
                        $this->logger->log('Removing old index ' . $indexToBeRemoved);
                    }
                }
            } catch (ApiException $exception) {
                $response = json_decode($exception->getResponse());
                $message = \sprintf('Nothing removed. ElasticSearch responded with status %s', $response->status);
                if (isset($response->error->type)) {
                    $this->logger->log(sprintf('%s, saying "%s: %s"', $message, $response->error->type, $response->error->reason));
                } else {
                    $this->logger->log(sprintf('%s, saying "%s"', $message, $response->error));
                }
            }
        }
        if ($removed === false) {
            $this->logger->log('Nothing to remove.');
        }
    }

    protected function outputErrorHandling()
    {
        if ($this->errorHandlingService->hasError() === false) {
            return;
        }

        $this->outputLine();
        /** @var ErrorInterface $error */
        foreach ($this->errorHandlingService as $error) {
            $this->outputLine('<error>Error</error> ' . $error->message());
        }
        $this->outputLine();
        $this->outputLine('<error>Check your logs for more information</error>');
    }

    /**
     * @param string $command
     * @param array $arguments
     * @throws Exception
     */
    protected function executeInternalCommand($command, array $arguments)
    {
        $this->outputLine();
        $commandIdentifier = 'flowpack.elasticsearch.contentrepositoryadaptor:nodeindex:' . $command;
        $status = Scripts::executeCommand($commandIdentifier, $this->flowSettings, true, $arguments);
        if ($status !== true) {
            throw new Exception(\vsprintf('Command: %s with parameters: %s', [$commandIdentifier, \json_encode($arguments)]), 1426767159);
        }
        $this->outputLine();
    }

    /**
     * @param array $arguments
     * @return int
     * @throws Exception
     */
    protected function executeBuildWorkspaceCommand(array $arguments)
    {
        ob_start(null, 1<<20);
        $commandIdentifier = 'flowpack.elasticsearch.contentrepositoryadaptor:nodeindex:buildworkspaceinternal';
        $status = Scripts::executeCommand($commandIdentifier, $this->flowSettings, true, $arguments);
        if ($status !== true) {
            throw new Exception(\vsprintf('Command: %s with parameters: %s', [$commandIdentifier, \json_encode($arguments)]), 1426767159);
        }
        $output = explode(\PHP_EOL, ob_get_clean());
        $count = (int)\array_pop($output);
        if (count($output) > 0) {
            foreach ($output as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $this->outputLine('<info>+</info> %s', [$line]);
            }
        }
        return $count < 1 ? 0 : $count;
    }

    /**
     * Create a new index with the given $postfix.
     *
     * @param string $postfix
     * @param array $dimensionValues
     * @return void
     */
    protected function createNewIndex($postfix, array $dimensionValues = [])
    {
        $this->nodeIndexer->setIndexNamePostfix($postfix);
        if ($this->nodeIndexer->getIndex()->exists() === true) {
            $this->logger->log(sprintf('Deleted index with the same postfix (%s)!', $postfix), LOG_WARNING);
            $this->nodeIndexer->getIndex()->delete();
        }
        $this->nodeIndexer->getIndex()->create();
        $this->logger->log('Created index ' . $this->nodeIndexer->getIndexName(), LOG_INFO);
        $this->logger->log('+ Dimensions: ' . \json_encode($dimensionValues), LOG_INFO);
    }

    /**
     * Apply the mapping to the current index.
     *
     * @return void
     */
    protected function applyMapping()
    {
        $nodeTypeMappingCollection = $this->nodeTypeMappingBuilder->buildMappingInformation($this->nodeIndexer->getIndex());
        foreach ($nodeTypeMappingCollection as $mapping) {
            /** @var Mapping $mapping */
            $mapping->apply();
        }
        $this->logger->log('+ Updated Mapping.', LOG_INFO);
    }

    protected function outputMemoryUsage()
    {
        $this->logger->log(vsprintf('Memory usage %s', [Files::bytesToSizeString(\memory_get_usage(true))]), LOG_INFO);
    }
}
