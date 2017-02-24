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

/**
 * Abstract Elasticsearch Query
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
     * These fields are not accepted in a count request and must therefore be removed before doing so
     *
     * @var array
     */
    protected $unsupportedFieldsInCountRequest = [];

    /**
     * @param array $request
     * @param array $unsupportedFieldsInCountRequest
     */
    public function __construct(array $request, array $unsupportedFieldsInCountRequest)
    {
        $this->request = $request;
        $this->unsupportedFieldsInCountRequest = $unsupportedFieldsInCountRequest;
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
    public function getRequestAsJson()
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
    }

    /**
     * {@inheritdoc}
     */
    public function aggregation($name, array $aggregationDefinition, $parentPath = '')
    {
        if (!array_key_exists('aggregations', $this->request)) {
            $this->request['aggregations'] = [];
        }

        if ((string)$parentPath !== '') {
            $this->addSubAggregation($parentPath, $name, $aggregationDefinition);
        } else {
            $this->request['aggregations'][$name] = $aggregationDefinition;
        }
    }

    /**
     * This is an low level method for internal usage.
     *
     * You can add a custom $aggregationConfiguration under a given $parentPath. The $parentPath foo.bar would
     * insert your $aggregationConfiguration under
     * $this->request['aggregations']['foo']['aggregations']['bar']['aggregations'][$name]
     *
     * @param string $parentPath The parent path to add the sub aggregation to
     * @param string $name The name to identify the resulting aggregation
     * @param array $aggregationConfiguration
     * @return QueryInterface
     * @throws Exception\QueryBuildingException
     */
    protected function addSubAggregation($parentPath, $name, $aggregationConfiguration)
    {
        // Find the parentPath
        $path =& $this->request['aggregations'];

        foreach (explode('.', $parentPath) as $subPart) {
            if ($path == null || !array_key_exists($subPart, $path)) {
                throw new Exception\QueryBuildingException(sprintf('The parent path segment "%s" could not be found when adding a sub aggregation to parent path "%s"', $subPart, $parentPath));
            }
            $path =& $path[$subPart]['aggregations'];
        }

        $path[$name] = $aggregationConfiguration;
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
    }

    /**
     * {@inheritdoc}
     */
    public function setValueByPath($path, $value)
    {
        $this->request = Arrays::setValueByPath($this->request, $path, $value);
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

    /**
     * @param array $request
     */
    public function replaceRequest(array $request)
    {
        $this->request = $request;
    }
}
