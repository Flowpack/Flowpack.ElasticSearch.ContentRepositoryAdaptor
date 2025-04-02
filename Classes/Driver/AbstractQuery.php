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

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Utility\Arrays;

/**
 * Abstract Elasticsearch Query
 */
abstract class AbstractQuery implements QueryInterface, \JsonSerializable, \ArrayAccess, ProtectedContextAwareInterface
{
    /**
     * The Elasticsearch request, as it is being built up.
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
     * Default parameters for the query_string filter used for fulltext search
     *
     * @var array
     */
    protected $queryStringParameters = [];

    /**
     * @param array $request
     * @param array $unsupportedFieldsInCountRequest
     * @param array $queryStringParameters
     */
    public function __construct(array $request, array $unsupportedFieldsInCountRequest, array $queryStringParameters)
    {
        $this->request = $request;
        $this->unsupportedFieldsInCountRequest = $unsupportedFieldsInCountRequest;
        $this->queryStringParameters = $queryStringParameters;
    }

    /**
     * Modify a part of the Elasticsearch Request denoted by $path, merging together
     * the existing values and the passed-in values.
     *
     * @param string $path
     * @param mixed $requestPart
     * @return AbstractQuery
     */
    public function setByPath(string $path, $requestPart): QueryInterface
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
    public function toArray(): array
    {
        return $this->prepareRequest();
    }

    /**
     * {@inheritdoc}
     * @throws \JsonException
     */
    public function getRequestAsJson(): string
    {
        return json_encode($this);
    }

    /**
     * {@inheritdoc}
     */
    public function addSortFilter(array $configuration): void
    {
        if (!isset($this->request['sort'])) {
            $this->request['sort'] = [];
        }
        $this->request['sort'][] = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function aggregation(string $name, array $aggregationDefinition, string $parentPath = ''): void
    {
        if (!array_key_exists('aggregations', $this->request)) {
            $this->request['aggregations'] = [];
        }

        if ($parentPath !== '') {
            $this->addSubAggregation($parentPath, $name, $aggregationDefinition);
        } else {
            $this->request['aggregations'][$name] = $aggregationDefinition;
        }
    }

    /**
     * {@inheritdoc}
     * @throws Exception\QueryBuildingException
     */
    public function moreLikeThis(array $like, array $fields = [], array $options = []): void
    {
        $moreLikeThis = $options;
        $moreLikeThis['like'] = $like;

        if (!empty($fields)) {
            $moreLikeThis['fields'] = $fields;
        }

        $this->appendAtPath('query.bool.filter.bool.must', ['more_like_this' => $moreLikeThis]);
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
     * @return void
     * @throws Exception\QueryBuildingException
     */
    protected function addSubAggregation(string $parentPath, string $name, array $aggregationConfiguration): void
    {
        // Find the parentPath
        $path =& $this->request['aggregations'];

        foreach (explode('.', $parentPath) as $subPart) {
            if ($path === null || !array_key_exists($subPart, $path)) {
                throw new Exception\QueryBuildingException(sprintf('The parent path segment "%s" could not be found when adding a sub aggregation to parent path "%s"', $subPart, $parentPath));
            }
            $path =& $path[$subPart]['aggregations'];
        }

        $path[$name] = $aggregationConfiguration;
    }

    /**
     * {@inheritdoc}
     */
    public function suggestions(string $name, array $suggestionDefinition): void
    {
        if (!array_key_exists('suggest', $this->request)) {
            $this->request['suggest'] = [];
        }

        $this->request['suggest'][$name] = $suggestionDefinition;
    }

    /**
     * {@inheritdoc}
     */
    public function highlight($fragmentSize, ?int $fragmentCount = null, int $noMatchSize = 150, string $field = 'neos_fulltext.*'): void
    {
        if ($fragmentSize === false) {
            // Highlighting is disabled.
            unset($this->request['highlight']);
            return;
        }

        $this->request['highlight']['fields'][$field] = [
            'fragment_size' => $fragmentSize,
            'no_match_size' => $noMatchSize,
            'number_of_fragments' => $fragmentCount
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function setValueByPath(string $path, $value): void
    {
        $this->request = Arrays::setValueByPath($this->request, $path, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function appendAtPath(string $path, array $data): void
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
        if ($offset === null) {
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
        return $this->request[$offset] ?? null;
    }

    /**
     * Prepare the final request array
     *
     * This method is useful if you extend the current query implementation.
     *
     * @return array
     */
    protected function prepareRequest(): array
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
    public function replaceRequest(array $request): void
    {
        $this->request = $request;
    }
}
