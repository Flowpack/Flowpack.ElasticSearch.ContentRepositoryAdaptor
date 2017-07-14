<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Utility;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;

/**
 * The elasticsearch client to be used by the content repository adapter. Singleton, can be injected.
 *
 * Used to:
 *
 * - make the ElasticSearch Client globally available
 * - allow to access the index to be used for reading/writing in a global way
 *
 * @Flow\Scope("singleton")
 */
class ElasticSearchClient extends \Flowpack\ElasticSearch\Domain\Model\Client
{
    /**
     * The index name to be used for querying (by default "typo3cr")
     *
     * @var string
     */
    protected $indexName;

    /**
     * MD5 hash of the content dimensions
     *
     * @var string
     */
    protected $dimensionsHash;

    /**
     * @var array
     */
    protected $dimensions;

    /**
     * @Flow\Inject
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * Called by the Flow object framework after creating the object and resolving all dependencies.
     *
     * @param integer $cause Creation cause
     */
    public function initializeObject($cause)
    {
        if ($cause === ObjectManagerInterface::INITIALIZATIONCAUSE_CREATED) {
            $settings = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.ContentRepository.Search');
            $this->indexName = $settings['elasticSearch']['indexName'];
        }
    }

    /**
     * @param string $dimensionsHash
     */
    public function setDimensions(array $dimensionValues = [])
    {
        $targetDimensions = array_map(function ($dimensionValues) {
            return [array_shift($dimensionValues)];
        }, $dimensionValues);
        $this->dimensions = $dimensionValues;
        $this->dimensionsHash = $targetDimensions !== [] ? Utility::sortDimensionValueArrayAndReturnDimensionsHash($targetDimensions) : null;
    }

    /**
     * @return string
     */
    public function getDimensionsHash()
    {
        return $this->dimensionsHash;
    }

    /**
     * @param \Closure $closure
     * @param array $dimensionValues
     */
    public function withDimensions(\Closure $closure, array $dimensionValues = [])
    {
        $previousDimensions = $this->dimensions;
        $this->setDimensions($dimensionValues);
        $closure();
        $this->setDimensions($previousDimensions);
    }

    /**
     * Get the index name to be used
     *
     * @return string
     */
    public function getIndexName()
    {
        return $this->dimensionsHash ? $this->indexName . '-' . $this->dimensionsHash : $this->indexName;
    }

    /**
     * Retrieve the index to be used for querying or on-the-fly indexing.
     * In ElasticSearch, this index is an *alias* to the currently used index.
     *
     * @return \Flowpack\ElasticSearch\Domain\Model\Index
     */
    public function getIndex()
    {
        return $this->findIndex($this->getIndexName());
    }
}
