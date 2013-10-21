<?php
/**
 * Created by JetBrains PhpStorm.
 * User: goldbeckm
 * Date: 17.10.13
 * Time: 14:41
 * To change this template use File | Settings | File Templates.
 */

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service;


class ElasticSearch {
	public $index;

	function __construct($server = 'http://localhost:9200'){
		$this->server = $server;
	}

	function call($path, $http = array()){
		if (!$this->index) throw new Exception('$this->index needs a value');
		$params = array(
			'http' => array
			(
				'method' => 'GET',
				'header'=>"Content-Type: multipart/form-data\r\n",
				'content' => $http
			)
		);
		return json_decode(file_get_contents($this->server . '/' . $this->index . '/' . $path, NULL, stream_context_create($params)));
		//return json_decode(file_get_contents($this->server . '/' . $this->index . '/' . $path, NULL, stream_context_create(array('http' => $http))));
	}

	//curl -X PUT http://localhost:9200/{INDEX}/
	function create(){
		$this->call(NULL, array('method' => 'PUT'));
	}

	//curl -X DELETE http://localhost:9200/{INDEX}/
	function drop(){
		$this->call(NULL, array('method' => 'DELETE'));
	}

	//curl -X GET http://localhost:9200/{INDEX}/_status
	function status(){
		return $this->call('_status');
	}

	//curl -X GET http://localhost:9200/{INDEX}/{TYPE}/_count -d {matchAll:{}}
	function count($type){
		return $this->call($type . '/_count', array('method' => 'GET', 'content' => '{ matchAll:{} }'));
	}

	//curl -X PUT http://localhost:9200/{INDEX}/{TYPE}/_mapping -d ...
	function map($type, $data){
		return $this->call($type . '/_mapping', array('method' => 'PUT', 'content' => $data));
	}

	//curl -X PUT http://localhost:9200/{INDEX}/{TYPE}/{ID} -d ...
	function add($type, $id, $data){
		return $this->call($type . '/' . $id, array('method' => 'PUT', 'content' => $data));
	}

	//curl -X GET http://localhost:9200/{INDEX}/{TYPE}/_search?q= ...
	function query($type, $q){
		return $this->call($type . '/_search?' . http_build_query(array('q' => $q)));
	}
}