<?php
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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use TYPO3\Eel\ProtectedContextAwareInterface;
use TYPO3\Flow\Utility\Arrays;
use TYPO3\TYPO3CR\Search\Search\QueryBuilderInterface;

/**
 * Default Filtered Query
 */
abstract class AbstractQuery implements QueryInterface, \JsonSerializable, \ArrayAccess, ProtectedContextAwareInterface
{
    /**
     * The ElasticSearch request, as it is being built up.
     *
     * @var array
     */
    protected $request = [];

    /**
     * @var QueryBuilderInterface
     */
    protected $queryBuilder;

    /**
     * These fields are not accepted in a count request and must therefore be removed before doing so
     *
     * @var array
     */
    protected $unsupportedFieldsInCountRequest = ['fields', 'sort', 'from', 'size', 'highlight', 'aggs', 'aggregations'];

    /**
     * @param QueryBuilderInterface $queryBuilder
     * @param array $request Override the default request
     */
    public function __construct(QueryBuilderInterface $queryBuilder, array $request = null)
    {
        $this->queryBuilder = $queryBuilder;
        if ($request !== null) {
            $this->request = $request;
        }
    }

    /**
     * Modify a part of the Elasticsearch Request denoted by $path, merging together
     * the existing values and the passed-in values.
     *
     * @param string $path
     * @param mixed $requestPart
     * @return QueryInterface
     */
    public function setByPath($path, $requestPart)
    {
        $valueAtPath = Arrays::getValueByPath($this->request, $path);
        if (is_array($valueAtPath)) {
            $result = Arrays::arrayMergeRecursiveOverrule($valueAtPath, $requestPart);
        } else {
            $result = $requestPart;
        }

        $this->request = Arrays::setValueByPath($this->request, $path, $result);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return $this->prepareRequest();
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestAsJSON()
    {
        return json_encode($this);
    }

    /**
     * {@inheritdoc}
     */
    public function addSortFilter($configuration)
    {
        if (!isset($this->request['sort'])) {
            $this->request['sort'] = [];
        }
        $this->request['sort'][] = $configuration;

        return $this->queryBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function aggregation($name, array $aggregationDefinition, $parentPath = null)
    {
        if (!array_key_exists('aggregations', $this->request)) {
            $this->request['aggregations'] = [];
        }

        if ($parentPath !== null) {
            $this->addSubAggregation($parentPath, $name, $aggregationDefinition);
        } else {
            $this->request['aggregations'][$name] = $aggregationDefinition;
        }

        return $this->queryBuilder;
    }

    /**
     * This is an low level method for internal usage.
     *
     * You can add a custom $aggregationConfiguration under a given $parentPath. The $parentPath foo.bar would
     * insert your $aggregationConfiguration under
     * $this->request['aggregations']['foo']['aggregations']['bar']['aggregations'][$name]
     *
     * @param $parentPath
     * @param $name
     * @param array $aggregationConfiguration
     * @return QueryInterface
     * @throws Exception\QueryBuildingException
     */
    protected function addSubAggregation($parentPath, $name, $aggregationConfiguration)
    {
        // Find the parentPath
        $path =& $this->request['aggregations'];

        foreach (explode(".", $parentPath) as $subPart) {
            if ($path == null || !array_key_exists($subPart, $path)) {
                throw new Exception\QueryBuildingException("The parent path " . $subPart . " could not be found when adding a sub aggregation");
            }
            $path =& $path[$subPart]['aggregations'];
        }

        $path[$name] = $aggregationConfiguration;

        return $this->queryBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function suggestions($name, array $suggestionDefinition)
    {
        if (!array_key_exists('suggest', $this->request)) {
            $this->request['suggest'] = [];
        }

        $this->request['suggest'][$name] = $suggestionDefinition;

        return $this->queryBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function highlight($fragmentSize, $fragmentCount = null)
    {
        if ($fragmentSize === false) {
            // Highlighting is disabled.
            unset($this->request['highlight']);
        } else {
            $this->request['highlight'] = [
                'fields' => [
                    '__fulltext*' => [
                        'fragment_size' => $fragmentSize,
                        'no_match_size' => $fragmentSize,
                        'number_of_fragments' => $fragmentCount
                    ]
                ]
            ];
        }

        return $this->queryBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function setValueByPath($path, $value)
    {
        $this->request = Arrays::setValueByPath($this->request, $path, $value);

        return $this->queryBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function appendAtPath($path, array $data)
    {
        $currentElement =& $this->request;
        foreach (explode('.', $path) as $pathPart) {
            if (!isset($currentElement[$pathPart])) {
                throw new Exception\QueryBuildingException('The element at path "' . $path . '" was not an array (failed at "' . $pathPart . '").', 1383716367);
            }
            $currentElement =& $currentElement[$pathPart];
        }
        $currentElement[] = $data;

        return $this->queryBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->prepareRequest();
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->request[] = $value;
        } else {
            $this->request[$offset] = $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return isset($this->request[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        unset($this->request[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return isset($this->request[$offset]) ? $this->request[$offset] : null;
    }

    /**
     * Prepare the final request array
     *
     * This method is useful if you extend the current query implementation.
     *
     * @return array
     */
    protected function prepareRequest()
    {
        return $this->request;
    }

    /**
     * All methods are considered safe
     *
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
