# TYPO3 Neos ElasticSearch Adapter

*supporting ElasticSearch Version 1.2.x and 1.3.x*

Created by Sebastian Kurfürst; contributions by Karsten Dambekalns and Robert Lemke.

This project connects the TYPO3 Content Repository (TYPO3CR) to ElasticSearch; enabling two
main functionalities:

* finding Nodes in TypoScript / Eel by arbitrary queries
* Full-Text Indexing of Pages and other Documents (of course including the full content)


Relevant Packages:

* `TYPO3.TYPO3CR.Search`: provides common functionality for searching TYPO3CR nodes,
  does not contain a search backend

* `Flowpack.ElasticSearch.ContentRepositoryAdaptor`: this package

* `Flowpack.SimpleSearch.ContentRepositoryAdaptor`: an alternative search backend (to be used
  instead of this package); storing the search index in SQLite

* `Flowpack.SearchPlugin`: search plugin for Neos


## Version 2 vs Version 1

* Version 1 is the initial, productive version of the Neos ElasticSearch adapter.
* Version 2 has a dependency on TYPO3.TYPO3CR.Search; which contains base functionality
  which is also relevant for other search implementations (like the SQLite based SimpleSearch).

The configuration from Version 1 to Version 2 has changed; here's what to change:

**elasticsearch.yaml**

Due to the fact that the default scripting language has changed from marvel to groovy since elasticsearch 1.3.0,
there is a need, depending on your running installation of ElasticSearch, to add following lines of configuration to your
ElasticSearch Configuration File `elasticsearch.yaml`.

If you are using ElasticSearch version 1.2 you have also to install groovy as a plugin. To install the plugin just run
the following command in the root folder of your elastic:

```
bin/plugin -install elasticsearch/elasticsearch-lang-groovy/2.2.0.
```

```
script.disable_dynamic: false
script.default_lang: groovy

```

For running with version > 1.3.x, what is recommended, it is sufficient to add following lines to your
`elasticsearch.yaml`.


```
script.groovy.sandbox.class_whitelist: java.util.LinkedHashMap
script.groovy.sandbox.receiver_whitelist:  java.util.Iterator, java.lang.Object, java.util.Map, java.util.Map$Entry
```

You can get further information about this topic here:

http://www.elasticsearch.org/blog/elasticsearch-1-3-0-released/
http://www.elasticsearch.org/blog/scripting-security/
http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/modules-scripting.html

**Settings.yaml**

1. Change the base namespace for configuration from `Flowpack.ElasticSearch.ContentRepositoryAdaptor`
   to `TYPO3.TYPO3CR.Search`. All further adjustments are made underneath this namespace:

2. (If it exists in your configuration:) Move `indexName` to `elasticSearch.indexName`

3. (If it exists in your configuration:) Move `log` to `elasticSearch.log`

4. search for `mapping` (inside `defaultConfigurationPerType.<typeName>`) and replace it by
   `elasticSearchMapping`.

5. Inside the `indexing` expressions (at `defaultConfigurationPerType.<typeName>`), replace
   `ElasticSearch.` by `Indexing.`.

**NodeTypes.yaml**

1. Replace `elasticSearch` by `search`. This replaces both `<YourNodeType>.elasticSearch`
   and `<YourNodeType>.properties.<propertyName>.elasticSearch`.

2. search for `mapping` (inside `<YourNodeType>.properties.<propertyName>.search`) and replace it by
   `elasticSearchMapping`.

3. Replace `ElasticSeach.fulltext` by `Indexing`

4. Search for `ElasticSearch.` (inside the `indexing` expressions) and replace them by `Indexing.`


## Building up the Index

The node index is updated on the fly, but during development you need to update it frequently.

In case of a mapping update, you need to reindex all nodes. Don't worry to do that in production;
the system transparently creates a new index, fills it completely, and when everything worked,
changes the index alias.


```
./flow nodeindex:build

 # if during development, you only want to index a few nodes, you can use "limit"
./flow nodeindex:build --limit 20

 # in order to remove old, non-used indices, you should use this command from time to time:
./flow nodeindex:cleanup
```


### Advanced Index Settings
If you need advanced settings you can define them in your *Settings.yaml*:

Example is from the Documentation of the used *Flowpack.ElasticSearch* Package

https://github.com/Flowpack/Flowpack.ElasticSearch/blob/master/Documentation/Indexer.rst

```
Flowpack:
	ElasticSearch:
		indexes:
			default:
				'twitter':
					analysis:
						filter:
							elision:
								type: 'elision'
								articles: [ 'l', 'm', 't', 'qu', 'n', 's', 'j', 'd' ]
					analyzer:
						custom_french_analyzer:
							tokenizer: 'letter'
							filter: [ 'asciifolding', 'lowercase', 'french_stem', 'elision', 'stop' ]
						tag_analyzer:
							tokenizer: 'keyword'
							filter: [ 'asciifolding', 'lowercase' ]
```

If you use multiple client configurations, please change the *default* key just below the *indexes*.


## Doing Arbitrary Queries

We'll first show how to do arbitrary ElasticSearch Queries in TypoScript. This is a more powerful
alternative to FlowQuery. In the long run, we might be able to integrate this API back into FlowQuery,
but for now it works well as-is.

Generally, ElasticSearch queries are done using the `Search` Eel helper. In case you want
to retrieve a *list of nodes*, you'll generally do:
```
nodes = ${Search.query(site)....execute()}
```

In case you just want to retrieve a *single node*, the form of a query is as follows:
```
nodes = ${q(Search.query(site)....execute()).get(0)}
```

To fetch the total number of hits a query returns, the form of a query is as follows:
```
nodes = ${Search.query(site)....count()}
```

All queries search underneath a certain subnode. In case you want to search "globally", you will
search underneath the current site node (like in the example above).

Furthermore, the following operators are supported:

* `nodeType("Your.Node:Type")`
* `exactMatch(key, value)`; supports simple types: `exactMatch('tag', 'foo')`, or node references: `exactMatch('author', authorNode)`
* `sortAsc('propertyName')` and `sortDesc('propertyName')` -- can also be used multiple times, e.g. `sortAsc('tag').sortDesc(`date')` will first sort by tag ascending, and then by date descending.
* `limit(5)` -- only return five results. If not specified, the default limit by ElasticSearch applies (which is at 10 by default)
* `from(5)` -- return the results starting from the 6th one
* `fulltext(...)` -- do a query_string query on the Fulltext Index

Furthermore, there is a more low-level operator which can be used to add arbitrary ElasticSearch filters:

* `queryFilter("filterType", {option1: "value1"})`

In order to debug the query more easily, the following operation is helpful:

* `log()` log the full query on execution into the ElasticSearch log (i.e. in `Data/Logs/ElasticSearch.log`)

### Example Queries

#### Finding all pages which are tagged in a special way and rendering them in an overview

Use Case: On a "Tag Overview" page, you want to show all pages being tagged in a certain way

Setup: You have two node types in a blog called `Acme.Blog:Post` and `Acme.Blog:Tag`, both
inheriting from `TYPO3.Neos:Document`. The `Post` node type has a property `tags` which is
of type `references`, pointing to `Tag` documents.

TypoScript setup:

```
 # for "Tag" documents, replace the main content area.
prototype(TYPO3.Neos:PrimaryContent).acmeBlogTag {
	condition = ${q(node).is('[instanceof Acme.Blog:Tag]')}
	type = 'Acme.Blog:TagPage'
}

 # The "TagPage"
prototype(Acme.Blog:TagPage) < prototype(TYPO3.TypoScript:Collection) {
	collection = ${Search.query(site).nodeType('Acme.Blog:Post').exactMatch('tags', node).sortDesc('creationDate').execute()}
	itemName = 'node'
	itemRenderer = Acme.Blog:SingleTag
}
prototype(Acme.Blog:SingleTag) < prototype(TYPO3.Neos:Template) {
	...
}
```


## Fulltext Search / Indexing

When searching in a fulltext index, we want to show Pages, or, generally speaking, everything
which is a `Document` node. However, the main content of a certain `Document` is often not stored
in the node itself, but inside its (`Content`) child nodes.

This is why we need some special functionality for indexing, which *adds the content of the inner
nodes* to the `Document` nodes where they belong to, to a field called `__fulltext` and
`__fulltextParts`.

Furthermore, we want that a fulltext match e.g. inside a headline is seen as *more important* than
a match inside the normal body text. That's why the `Document` node not only contains one field with
all the texts, but multiple "buckets" where text is added to: One field which contains everything
deemed as "very important" (`__fulltext.h1`), one which is "less important" (`__fulltext.h2`),
and finally one for the plain text (`__fulltext.text`). All of these fields add themselves to the
ElasticSearch `_all` field, and are configured with different `boost` values.

In order to search this index, you can just search inside the `_all` field with an additional limitation
of `__typeAndSupertypes` containing `TYPO3.Neos:Document`.

**For a search user interface, checkout the Flowpack.SearchPlugin package**


## Advanced: Configuration of Indexing

**Normally, this does not need to be touched, as this package supports all TYPO3 Neos data types natively.**

Indexing of properties is configured at two places. The defaults per-data-type are configured
inside `TYPO3.TYPO3CR.Search.defaultConfigurationPerType` of `Settings.yaml`.
Furthermore, this can be overridden using the `properties.[....].search` path inside
`NodeTypes.yaml`.

This configuration contains two parts:

* Underneath `elasticSearchMapping`, the ElasticSearch property mapping can be defined.
* Underneath `indexing`, an Eel expression which processes the value before indexing has to be
  specified. It has access to the current `value` and the current `node`.

Example (from the default configuration):
```
 # Settings.yaml
TYPO3:
  TYPO3CR:
    Search:
      defaultConfigurationPerType:

        # strings should, by default, not be included in the _all field; and
        # indexing should just use their simple value.
        string:
          elasticSearchMapping:
            type: string
            include_in_all: false
          indexing: '${value}'
```

```
 # NodeTypes.yaml
'TYPO3.Neos:Timable':
  properties:
    '_hiddenBeforeDateTime':
      search:

        # a date should be mapped differently, and in this case we want to use a date format which
        # ElasticSearch understands
        elasticSearchMapping:
          type: date
          include_in_all: false
          format: 'date_time_no_millis'
        indexing: '${(node.hiddenBeforeDateTime ? node.hiddenBeforeDateTime.format("Y-m-d\TH:i:s") + "Z" : null)}'
```

There are a few indexing helpers inside the `Indexing` namespace which are usable inside the
`indexing` expression. In most cases, you don't need to touch this, but they were needed to build up
the standard indexing configuration:

* `Indexing.buildAllPathPrefixes`: for a path such as `foo/bar/baz`, builds up a list of path
  prefixes, e.g. `['foo', 'foo/bar', 'foo/bar/baz']`.
* `Indexing.extractNodeTypeNamesAndSupertypes(NodeType)`: extracts a list of node type names for
  the passed node type and all of its supertypes
* `Indexing.convertArrayOfNodesToArrayOfNodeIdentifiers(array $nodes)`: convert the given nodes to
  their node identifiers.



## Advanced: Fulltext Indexing

In order to enable fulltext indexing, every `Document` node must be configured as *fulltext root*. Thus,
the following is configured in the default configuration:

```
'TYPO3.Neos:Document':
  search:
    fulltext:
      isRoot: true
```

A *fulltext root* contains all the *content* of its non-document children, such that when one searches
inside these texts, the document itself is returned as result.

In order to specify how the fulltext of a property in a node should be extracted, this is configured
in `NodeTypes.yaml` at `properties.[propertyName].search.fulltextExtractor`.

An example:

```
'TYPO3.Neos.NodeTypes:Text':
  properties:
    'text':
      search:
        fulltextExtractor: '${Indexing.extractHtmlTags(value)}'

'My.Blog:Post':
  properties:
    title:
      search:
        fulltextExtractor: ${Indexing.extractInto('h1', value)}
```


## Fulltext Searching / Search Plugin

**For a search user interface, checkout the Flowpack.SearchPlugin package**


## Debugging

In order to understand what's going on, the following commands are helpful:

* use `./flow nodeindex:showMapping` to show the currently defined ElasticSearch Mapping
* use the `.log()` statement inside queries to dump them to the ElasticSearch Log
* the logfile `Data/Logs/ElasticSearch.log` contains loads of helpful information.