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

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Configuration\ConfigurationManager;
use TYPO3\Neos\Domain\Service\ContentContextFactory;

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
     * The language suffix
     *
     * @var string
     */
    protected $languageDimension;

    /**
     * @Flow\Inject
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @Flow\Inject
     * @var ContentContextFactory
     */
    protected $contextFactory;


    /**
     * Called by the Flow object framework after creating the object and resolving all dependencies.
     *
     * @param integer $cause Creation cause
     */
    public function initializeObject($cause)
    {
        if ($cause === \TYPO3\Flow\Object\ObjectManagerInterface::INITIALIZATIONCAUSE_CREATED) {
            $settings = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.TYPO3CR.Search');
            $this->indexName = $settings['elasticSearch']['indexName'];
        }
        $instance = current($this->contextFactory->getInstances());
        if ($instance) {
            $dimensions = $instance->getDimensions();
            $this->setDimension($dimensions['language'][0]);
        }

    }

    /**
     * Get the index name to be used
     *
     * @return string
     */
    public function getIndexName()
    {
        return $this->indexName . $this->languageDimension;
    }

    /**
     * Set the index language dimension
     *
     * @return string
     */
    public function setDimension($languageDimension)
    {
        $this->languageDimension = '-'.$languageDimension;
    }


    /**
     * Retrieve the index to be used for querying or on-the-fly indexing.
     * In ElasticSearch, this index is an *alias* to the currently used index.
     *
     * @return \Flowpack\ElasticSearch\Domain\Model\Index
     */
    public function getIndex()
    {
        return $this->findIndex($this->indexName . $this->languageDimension);
    }
}
