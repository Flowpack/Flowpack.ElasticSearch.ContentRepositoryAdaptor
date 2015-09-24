<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service;

/**
 * Created by JetBrains PhpStorm.
 * User: goldbeckm
 * Date: 17.10.13
 * Time: 14:41
 * To change this template use File | Settings | File Templates.
 */
class ElasticSearch
{
    /**
     * @var string
     */
    public $index;

    /**
     * @param string $server
     */
    public function __construct($server = 'http://localhost:9200')
    {
        $this->server = $server;
    }

    /**
     * @param string $path
     * @param array $http
     * @return mixed
     * @throws \Exception
     */
    public function call($path, array $http = array())
    {
        if (!$this->index) {
            throw new \Exception('$this->index needs a value');
        }
        $params = array(
            'http' => array(
                'method' => 'GET',
                'header' => "Content-Type: multipart/form-data\r\n",
                'content' => $http
            )
        );

        return json_decode(file_get_contents($this->server . '/' . $this->index . '/' . $path, null, stream_context_create($params)));
        //return json_decode(file_get_contents($this->server . '/' . $this->index . '/' . $path, NULL, stream_context_create(array('http' => $http))));
    }

    /**
     * curl -X PUT http://localhost:9200/{INDEX}/
     *
     * @return void
     */
    public function create()
    {
        $this->call(null, array('method' => 'PUT'));
    }

    /**
     * curl -X DELETE http://localhost:9200/{INDEX}/
     *
     * @return void
     */
    public function drop()
    {
        $this->call(null, array('method' => 'DELETE'));
    }

    /**
     * curl -X GET http://localhost:9200/{INDEX}/_status
     *
     * @return mixed
     */
    public function status()
    {
        return $this->call('_status');
    }

    /**
     * curl -X GET http://localhost:9200/{INDEX}/{TYPE}/_count -d {matchAll:{}}
     *
     * @param string $type
     * @return mixed
     */
    public function count($type)
    {
        return $this->call($type . '/_count', array('method' => 'GET', 'content' => '{ matchAll:{} }'));
    }

    /**
     * curl -X PUT http://localhost:9200/{INDEX}/{TYPE}/_mapping -d ...
     *
     * @param string $type
     * @param mixed $data
     * @return mixed
     */
    public function map($type, $data)
    {
        return $this->call($type . '/_mapping', array('method' => 'PUT', 'content' => $data));
    }

    /**
     * curl -X PUT http://localhost:9200/{INDEX}/{TYPE}/{ID} -d ...
     *
     * @param string $type
     * @param string $id
     * @param mixed $data
     * @return mixed
     */
    public function add($type, $id, $data)
    {
        return $this->call($type . '/' . $id, array('method' => 'PUT', 'content' => $data));
    }

    /**
     * curl -X GET http://localhost:9200/{INDEX}/{TYPE}/_search?q= ...
     *
     * @param string $type
     * @param string $q
     * @return mixed
     */
    public function query($type, $q)
    {
        return $this->call($type . '/_search?' . http_build_query(array('q' => $q)));
    }
}
