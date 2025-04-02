<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version6\Mapping;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\NodeTypeIndexingConfiguration;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\AbstractNodeTypeMappingBuilder;
use Flowpack\ElasticSearch\Domain\Model\Index;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Flowpack\ElasticSearch\Mapping\MappingCollection;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Error\Messages\Result;
use Neos\Error\Messages\Warning;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class NodeTypeMappingBuilder extends AbstractNodeTypeMappingBuilder
{
    /**
     * @var NodeTypeIndexingConfiguration
     * @Flow\Inject
     */
    protected $nodeTypeIndexingConfiguration;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;


    /**
     * Builds a Mapping Collection from the configured node types
     *
     * @return MappingCollection<\Flowpack\ElasticSearch\Domain\Model\Mapping>
     * @throws Exception
     */
    public function buildMappingInformation(ContentRepositoryId $contentRepositoryId, Index $index): MappingCollection
    {
        $this->lastMappingErrors = new Result();

        $mappings = new MappingCollection(MappingCollection::TYPE_ENTITY);

        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);

        /** @var \Neos\ContentRepository\Core\NodeType\NodeType $nodeType */
        foreach ($contentRepository->getNodeTypeManager()->getNodeTypes() as $nodeTypeName => $nodeType) {
            if ($nodeTypeName === 'unstructured' || $nodeType->isAbstract()) {
                continue;
            }

            if ($this->nodeTypeIndexingConfiguration->isIndexable($nodeType) === false) {
                continue;
            }

            $mapping = new Mapping($index->findType($nodeTypeName));
            $fullConfiguration = $nodeType->getFullConfiguration();
            if (isset($fullConfiguration['search']['elasticSearchMapping'])) {
                $fullMapping = $fullConfiguration['search']['elasticSearchMapping'];
                $mapping->setFullMapping($fullMapping);
            }

            foreach ($nodeType->getProperties() as $propertyName => $propertyConfiguration) {
                // This property is configured to not be index, so do not add a mapping for it
                if (isset($propertyConfiguration['search']) && array_key_exists('indexing', $propertyConfiguration['search']) && $propertyConfiguration['search']['indexing'] === false) {
                    continue;
                }

                if (isset($propertyConfiguration['search']['elasticSearchMapping'])) {
                    if (is_array($propertyConfiguration['search']['elasticSearchMapping'])) {
                        $propertyMapping = array_filter($propertyConfiguration['search']['elasticSearchMapping'], static function ($value) {
                            return $value !== null;
                        });
                        $mapping->setPropertyByPath($propertyName, $propertyMapping);
                    }
                } elseif (isset($propertyConfiguration['type'], $this->defaultConfigurationPerType[$propertyConfiguration['type']]['elasticSearchMapping'])) {
                    if (is_array($this->defaultConfigurationPerType[$propertyConfiguration['type']]['elasticSearchMapping'])) {
                        $mapping->setPropertyByPath($propertyName, $this->defaultConfigurationPerType[$propertyConfiguration['type']]['elasticSearchMapping']);
                    }
                } else {
                    $this->lastMappingErrors->addWarning(new Warning('Node Type "' . $nodeTypeName . '" - property "' . $propertyName . '": No ElasticSearch Mapping found.'));
                }
            }

            $mappings->add($mapping);
        }

        return $mappings;
    }
}
