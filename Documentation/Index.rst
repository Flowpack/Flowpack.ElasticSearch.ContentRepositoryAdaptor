Flowpack.ElasticSearch.ContentRepositoryAdaptor
===============================================

TYPO3 Flow Package for indexing Nodes from TYPO3 Neos to ElasticSearch.

..warning: This is definitely work in progress and neither the APIs nor the code is
  stable yet. Don't use it in your applications or projects yet.

Setup
-----

To be able indexing nodes into ElasticSearch there must be an index at all.
This can be created on console like this when the ElasticSearch Server is running::

	curl -XPUT 'http://localhost:9200/typo3cr/'

Other wise you get an error message like this::

	{
		"error" : "IndexMissingException[[typo3cr] missing]",
		"status" : 404
	}

To be sure that the 'typo3cr' index was created use the following url in your browser::

	http://localhost:9200/typo3cr/_search?pretty

The output should be like this::

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

It is recommended to use an index name that corresponds to the project you are indexing to avoid
clashes by using the same index in different projects. The index name can be changed in the
settings.