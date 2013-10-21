WIP
===

Flowpack.ElasticSearch.ContentRepositoryAdaptor
===============================================

TYPO3 Flow Package for indexing Nodes from TYPO3 Neos to ElasticSearch

Setup
-----

To be able indexing nodes into ElasticSearch there must be an index at all. This has to be 'neos'.
This can be created on console like this when the ElasticSearch Server is running.


		curl -XPUT 'http://localhost:9200/neos/'

Other wise you get an error message like this.

		{
 			"error" : "IndexMissingException[[neos] missing]",
  			"status" : 404
		}

To be sure that the 'neos' index was created use the following url in your browser

		http://localhost:9200/neos/_search?pretty


The output should be like this

		{
  			"took" : 1,
  			"timed_out" : false,
  			"_shards" : {
	    		"total" : 5,
	    		"successful" : 5,
	    		"failed" : 0
	  		},
	  		"hits" : {
	    		"total" : 0,
	    		"max_score" : null,
	    		"hits" : [ ]
	  		}
		}