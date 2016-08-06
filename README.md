[![Build Status](https://travis-ci.org/Flowpack/Flowpack.ElasticSearch.ContentRepositoryAdaptor.svg)](https://travis-ci.org/Flowpack/Flowpack.ElasticSearch.ContentRepositoryAdaptor)

# Neos Elasticsearch Adapter

*supporting Elasticsearch versions 1.2.x to 1.7.x*

Created by Sebastian Kurfürst; [contributions by Karsten Dambekalns, Robert Lemke and others](https://github.com/Flowpack/Flowpack.ElasticSearch.ContentRepositoryAdaptor/graphs/contributors).

This project connects the Neos Content Repository (TYPO3CR) to Elasticsearch; enabling two
main functionalities:

* finding Nodes in TypoScript / Eel by arbitrary queries
* Full-Text Indexing of Pages and other Documents (of course including the full content)


Relevant Packages:

* `TYPO3.TYPO3CR.Search`: provides common functionality for searching TYPO3CR nodes,
  does not contain a search backend

* `Flowpack.ElasticSearch`: provides common code for working with Elasticsearch

* `Flowpack.ElasticSearch.ContentRepositoryAdaptor`: this package

* `Flowpack.SimpleSearch.ContentRepositoryAdaptor`: an alternative search backend (to be used
  instead of this package); storing the search index in SQLite

* `Flowpack.SearchPlugin`: search plugin for Neos


## Installation

```
// for development (Master; Tested on Neos 2.0)
composer require 'typo3/typo3cr-search:@dev'
composer require 'flowpack/elasticsearch-contentrepositoryadaptor:@dev'

composer require 'flowpack/searchplugin:@dev'
```

Now, add the routes as described in the [README of Flowpack.SearchPlugin](https://github.com/skurfuerst/Flowpack.SearchPlugin)
as the **first route** in Configuration/Routes.yaml.

Then, ensure to update `<your-elasticsearch>/config/elasticsearch.yml` as explained below; then start Elasticsearch.

Finally, run `./flow nodeindex:build`, and add the search plugin to your page. It should "just work".

## Elasticsearch Configuration file elasticsearch.yml

There is a need, depending on your version of Elasticsearch, to add the following lines of configuration to your
Elasticsearch Configuration File `<your-elasticsearch>/config/elasticsearch.yml`.

### Needed Configuration in configuration.yml for Elasticsearch 1.6.x and 1.7.x

In verson 1.6 the `script.disable_dynamic` settings and the Groovy sandbox as such [have been deprecated]
(https://www.elastic.co/guide/en/elasticsearch/reference/1.6/modules-scripting.html#enable-dynamic-scripting).
You may continue to use the settings for version 1.5.x, but this is what would be the correct configuration for 1.6.x and 1.7.x.

```
# The following settings are absolutely required for the CR adaptor to work
script.file: on
script.engine.groovy.inline: sandbox
script.engine.groovy.indexed: sandbox

script.groovy.sandbox.class_whitelist: java.util.LinkedHashMap
script.groovy.sandbox.receiver_whitelist:  java.util.Iterator, java.lang.Object, java.util.Map, java.util.Map$Entry
script.groovy.sandbox.enabled: true

# the following settings secure your cluster
cluster.name: [PUT_YOUR_CUSTOM_NAME_HERE]
network.host: 127.0.0.1

# the following settings are well-suited for smaller Elasticsearch instances (e.g. as long as you can stay on one host)
index.number_of_shards: 1
index.number_of_replicas: 0
```

### Needed Configuration in configuration.yml for Elasticsearch 1.4.x and 1.5.x

```
# The following settings are absolutely required for the CR adaptor to work
script.disable_dynamic: sandbox
script.groovy.sandbox.class_whitelist: java.util.LinkedHashMap
script.groovy.sandbox.receiver_whitelist:  java.util.Iterator, java.lang.Object, java.util.Map, java.util.Map$Entry
script.groovy.sandbox.enabled: true

# the following settings secure your cluster
cluster.name: [PUT_YOUR_CUSTOM_NAME_HERE]
network.host: 127.0.0.1

# the following settings are well-suited for smaller Elasticsearch instances (e.g. as long as you can stay on one host)
index.number_of_shards: 1
index.number_of_replicas: 0
```

### Needed Configuration in configuration.yml for Elasticsearch 1.3.x

```
# The following settings are absolutely required for the CR adaptor to work
script.groovy.sandbox.class_whitelist: java.util.LinkedHashMap
script.groovy.sandbox.receiver_whitelist:  java.util.Iterator, java.lang.Object, java.util.Map, java.util.Map$Entry

# the following settings secure your cluster
cluster.name: [PUT_YOUR_CUSTOM_NAME_HERE]
network.host: 127.0.0.1

# the following settings are well-suited for smaller Elasticsearch instances (e.g. as long as you can stay on one host)
index.number_of_shards: 1
index.number_of_replicas: 0
```

You can get further information about this topic here:

http://www.elasticsearch.org/blog/elasticsearch-1-3-0-released/
http://www.elasticsearch.org/blog/scripting-security/
http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/modules-scripting.html

## Needed Configuration for Elasticsearch 1.2.x


If you are using Elasticsearch version 1.2 you have also to install groovy as a plugin. To install the plugin just run
the following command in the root folder of your elastic:

```
bin/plugin -install elasticsearch/elasticsearch-lang-groovy/2.2.0.
```

```
script.disable_dynamic: false
script.default_lang: groovy

```

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

We'll first show how to do arbitrary Elasticsearch Queries in TypoScript. This is a more powerful
alternative to FlowQuery. In the long run, we might be able to integrate this API back into FlowQuery,
but for now it works well as-is.

Generally, Elasticsearch queries are done using the `Search` Eel helper. In case you want
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
* `exactMatch('propertyName', value)`; supports simple types: `exactMatch('tag', 'foo')`, or node references: `exactMatch('author', authorNode)`
* `greaterThan('propertyName', value)` -- range filter with property values greater than the given value
* `greaterThanOrEqual('propertyName', value)` -- range filter with property values greater than or equal to the given value
* `lessThan('propertyName', value)` -- range filter with property values less than the given value
* `lessThanOrEqual('propertyName', value)` -- range filter with property values less than or equal to the given value
* `sortAsc('propertyName')` and `sortDesc('propertyName')` -- can also be used multiple times, e.g. `sortAsc('tag').sortDesc(`date')`
   will first sort by tag ascending, and then by date descending.
* `limit(5)` -- only return five results. If not specified, the default limit by Elasticsearch applies (which is at 10 by default)
* `from(5)` -- return the results starting from the 6th one
* `fulltext(...)` -- do a query_string query on the Fulltext Index

Furthermore, there is a more low-level operator which can be used to add arbitrary Elasticsearch filters:

* `queryFilter("filterType", {option1: "value1"})`

At lowest level, there is the `request` operator which allows to modify the request in arbitrary manner. Note that the existing request is merged with the passed-in type in case it is an array:

* `request('query.filtered.query.bool.minimum_should_match', 1)`
* `request('query.filtered.query.bool', {"minimum_should_match": 1})`

In order to debug the query more easily, the following operation is helpful:

* `log()` log the full query on execution into the Elasticsearch log (i.e. in `Data/Logs/ElasticSearch.log`)

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

#### Making OR queries

There's no OR operator provided in this package, so you need to use a custom Elasticsearch query filter for that:

```
....queryFilter('bool', {should: [
	{term: {tags: tagNode.identifier}},
	{term: {places: tagNode.identifier}},
	{term: {projects: tagNode.identifier}}
]})
```

## Aggregations

Aggregation is an easy way to aggregate your node data in different ways. Elasticsearch provides a couple of different types of
aggregations. Check `https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations.html` for more
info about aggregations. You can use them to get some simple aggregations like min, max or average values for
your node data. Aggregations also allows you to build a complex filter for e.g. a product search or statistics.

**Aggregation methods**
Right now there are two methods implemented. One generic `aggregation` function that allows you to add any kind of
aggregation definition and a pre-configured `fieldBasedAggregation`. Both methods can be added to your TS search query.
You can nest aggregations by providing a parent name.

* `aggregation($name, array $aggregationDefinition, $parentPath = NULL)` -- generic method to add a $aggregationDefinition under a path $parentPath with the name $name
* `fieldBasedAggregation($name, $field, $type = "terms", $parentPath = NULL)` -- adds a simple filed based Aggregation of type $type with name $name under path $parentPath. Used for simple aggregations like sum, avg, min, max or terms


### Examples

#### Add a average aggregation

To add an average aggregation you can use the fieldBasedAggregation. This snippet would add an average aggregation for
a property price:
```
nodes = ${Search.query(site)...fieldBasedAggregation("avgprice", "price", "avg").execute()}
```
Now you can access your aggregations inside your fluid template with
```
{nodes.aggregations}
```

#### Create a nested aggregation

In this scenario you could have a node that represents a product with the properties price and color. If you would like
to know the average price for all your colors you just nest an aggregation in your TypoScript:
```
nodes = ${Search.query(site)...fieldBasedAggregation("colors", "color").fieldBasedAggregation("avgprice", "price", "avg", "colors").execute()}
```
The first `fieldBasedAggregation` will add a simple terms aggregation (https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-terms-aggregation.html)
with the name colors. So all different colors of your nodetype will be listed here.
The second `fieldBasedAggregation` will add another sub-aggregation named avgprice below your colors-aggregation.

You can nest even more aggregations like this:
```
fieldBasedAggregation("anotherAggregation", "field", "avg", "colors.avgprice")
```

#### Add a custom aggregation

To add a custom aggregation you can use the `aggregation()` method. All you have to do is to provide an array with your
aggregation definition. This example would do the same as the fieldBasedAggregation would do for you:
```
aggregationDefinition = TYPO3.TypoScript:RawArray {
	terms = TYPO3.TypoScript:RawArray {
		field = "color"
	}
}
nodes = ${Search.query(site)...aggregation("color", this.aggregationDefinition).execute()}
```

#### Product filter

This is a more complex scenario. With this snippet we will create a full product filter based on your selected Nodes. Imagine
an NodeTye ProductList with an property `products`. This property contains a comma separated list of sku's. This could also
be a reference on other products.

```
prototype(Vendor.Name:FilteredProductList) < prototype(TYPO3.Neos:Content)
prototype(Vendor.Name:FilteredProductList) {

	// Create SearchFilter for products
	searchFilter = TYPO3.TypoScript:RawArray {
		sku = ${String.split(q(node).property("products"), ",")}
	}

	# Search for all products that matches your queryFilter and add aggregations
	filter = ${Search.query(site).nodeType("Vendor.Name:Product").queryFilterMultiple(this.searchFilter, "must").fieldBasedAggregation("color", "color").fieldBasedAggregation("size", "size").execute()}

    # Add more filter if get/post params are set
    searchFilter.color = ${request.arguments.color}
    searchFilter.color.@if.onlyRenderWhenFilterColorIsSet = ${request.arguments.color != ""}
    searchFilter.size = ${request.arguments.size}
    searchFilter.size.@if.onlyRenderWhenFilterSizeIsSet = ${request.arguments.size != ""}

    # filter your products
    products = ${Search.query(site).nodeType("Vendor.Name:Product").queryFilterMultiple(this.searchFilter, "must").execute()}

    # don't cache this element
	@cache {
		mode = 'uncached'
		context {
			1 = 'node'
			2 = 'site'
		}
	}
```

In the first lines we will add a new searchFilter variable and add your selected sku's as a filter. Based on this selection
we will add two aggregations of type terms. You can access the filter in your template with `{filter.aggregation}`. With
this information it is easy to create a form with some select fields with all available options. If you submit the form
just call the same page and add the get parameter color and/or size.
The next lines will parse those parameters and add them to the searchFilter. Based on your selection all products will
be fetched and passed to your template.


**Important notice**

If you do use the terms filter be aware of Elasticsearchs analyze functionality for strings. You might want to disable this
for all your filterable properties, or else filtering won't work on them properly:
```
'Vendor.Name:Product'
  properties:
    color:
      type: string
      defaultValue: ''
      search:
        elasticSearchMapping:
          type: "string"
          include_in_all: false
          index: 'not_analyzed'
```

## Sorting

This package adapts Elasticsearchs sorting capabilities. You can add multiple sort operations to your query.
Right now there are three methods you can use:

* `sortAsc('propertyName')`
* `sortDesc('propertyName')`
* `sort('configuration')`

Just append those method to your query like this:
```
# Sort ascending by property title

nodes = ${q(Search.query(site).....sortAsc("title").execute())}

# Sort for multiple properties

nodes = ${q(Search.query(site).....sortAsc("title").sortDesc("name").execute())}

# Custom sort operation

geoSorting = TYPO3.TypoScript:RawArray {
    _geo_distance = TYPO3.TypoScript:RawArray {
        latlng = TYPO3.TypoScript:RawArray {
            lat = 51.512711
            lon = 7.453084
        }
        order = "plane"
        unit = "km"
        distance_type = "sloppy_arc"
    }
}
nodes = ${Search.query(site).....sort(this.geoSorting).execute()}

```
Check https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-sort.html for more configuration
options.

### Example with pagination and sort by distance

This is how a more complex example could look like. Imagine you a want to render a list of nodes and in addition to each
node you want to display the distance to a specific point.

First of all you have to define a property in your NodeTypes.yaml for your node where to store lat/lon information's:
```
'Vendor.Name:Retailer':
  properties:
    'latlng':
      type: string
      search:
        elasticSearchMapping:
          type: "geo_point"
```

Query your nodes in your TypoScript:
```
geoSorting = TYPO3.TypoScript:RawArray {
    _geo_distance = TYPO3.TypoScript:RawArray {
        latlng = TYPO3.TypoScript:RawArray {
            lat = 51.512711
            lon = 7.453084
        }
        order = "plane"
        unit = "km"
        distance_type = "sloppy_arc"
    }
}
nodes = ${Search.query(site).nodeType('Vendor.Name:Retailer').sort(this.geoSorting)}
```

Now you can paginate that nodes in your template. To get your actually distance for each node use
the `GetHitArrayForNodeViewHelper`:
```
{namespace es=Flowpack\ElasticSearch\ContentRepositoryAdaptor\ViewHelpers}

<typo3cr:widget.paginate query="{nodes}" as="paginatedNodes">
    <f:for each="{paginatedNodes}" as="singleNode">
        {singleNode.name} - <es:getHitArrayForNode queryResultObject="{nodes}" node="{singleNode}" path="sort.0" />
    </f:for>
</typo3cr:widget.paginate>

```

The ViewHelper will use \TYPO3\Flow\Utility\Arrays::getValueByPath() to return a specified path. So you can make use
of an array or a string. Check the documentation \TYPO3\Flow\Utility\Arrays::getValueByPath() for more informations.

**Important notice**

The ViewHelper GetHitArrayForNode will return the raw hit result array. The path poperty allows you to access some
specific data like the the sort data. If there is only one value for your path the value will be returned.
If there is more data the full array will be returned by GetHitArrayForNode-VH. So you might have to use the
ForViewHelper to access your sort values.


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
Elasticsearch `_all` field, and are configured with different `boost` values.

In order to search this index, you can just search inside the `_all` field with an additional limitation
of `__typeAndSupertypes` containing `TYPO3.Neos:Document`.

**For a search user interface, checkout the Flowpack.SearchPlugin package**

## Suggestions

Elasticsearch offers an easy way to get query suggestions based on your query. Check
`https://www.elastic.co/guide/en/elasticsearch/reference/current/search-suggesters.html` for more information about how
you can build and use suggestion in your search.

**Suggestion methods implemented**

There are two methods implemented. `suggestions` is a generic one that allows to build the suggestion query of your
dreams. The other method is `termSuggestions` and is meant for basic term suggestions. They can be added to your totaly
awesome TS search query.

* `suggestions($name, array $suggestionDefinition)` -- generic method to be filled with your own suggestionQuery
* `termSuggestions($term, $field = '_all', $name = 'suggestions'` -- simple term suggestion query on all fields

### Examples

#### Add a simple suggestion to a query

Simple suggestion that returns a suggestion based on the sent term

```
suggestions = $(Search.query(site)...termSuggestions('someTerm')}
```
You can access your suggestions inside your fluid template with
```
{nodes.suggestions}
```

### Add a custom suggestion

Phrase query that returns query suggestions

```
suggestionsQueryDefinition = TYPO3.TypoScript:RawArray {
    text = 'some Text'
    simple_phrase = TYPO3.TypoScript:RawArray {
        phrase = TYPO3.TypoScript:RawArray {
            analyzer = 'body'
            field = 'bigram'
            size = 1
            real_world_error_likelihood = 0.95
            ...
        }
    }
}
suggestions = ${Search.query(site)...suggestions('my_suggestions', this.suggestionsQueryDefinition)}
```

## Advanced: Configuration of Indexing

**The default configuration supports most usecases and often may not need to be touched, as this package comes
with sane defaults for all Neos data types.**

Indexing of properties is configured at two places. The defaults per-data-type are configured
inside `TYPO3.TYPO3CR.Search.defaultConfigurationPerType` of `Settings.yaml`.
Furthermore, this can be overridden using the `properties.[....].search` path inside
`NodeTypes.yaml`.

This configuration contains two parts:

* Underneath `elasticSearchMapping`, the Elasticsearch property mapping can be defined.
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
        # Elasticsearch understands
        elasticSearchMapping:
          type: DateTime
          include_in_all: false
          format: 'date_time_no_millis'
        indexing: '${(node.hiddenBeforeDateTime ? Date.format(node.hiddenBeforeDateTime, "Y-m-d\TH:i:sP") : null)}'
```

If your nodetypes schema defines custom properties of type DateTime, you have got to provide similar configuration for
them as well in your `NodeTypes.yaml`, or else they will not be indexed correctly.

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


## Working with Dates

As a default, Elasticsearch indexes dates in the UTC Timezone. In order to have it index using the timezone
currently configured in PHP, the configuration for any property in a node which represents a date should look like this:

```
'My.Blog:Post':
  properties:
    date:
      search:
        elasticSearchMapping:
          type: 'date'
          format: 'date_time_no_millis'
        indexing: '${(value ? Date.format(value, "Y-m-d\TH:i:sP") : null)}'
```

This is important so that Date- and Time-based searches work as expected, both when using formatted DateTime strings and
when using relative DateTime calculations (eg.: `now`, `now+1d`).

If you want to filter items by date, e.g. to show items with date later than today, you can create a query like this:

```
${...greaterThan('date', Date.format(Date.Now(), "Y-m-d\TH:i:sP"))...}
```

For more information on Elasticsearch's Date Formats,
[click here](http://www.elastic.co/guide/en/elasticsearch/reference/current/mapping-date-format.html).


## Working with Assets / Attachments

If you want to index attachments, you need to install the [Elasticsearch Attachment Plugin](https://github.com/elastic/elasticsearch-mapper-attachments).
Then, you can add the following to your `Settings.yaml`:

```
TYPO3:
  TYPO3CR:
    Search:
      defaultConfigurationPerType:
        'TYPO3\Media\Domain\Model\Asset':
          elasticSearchMapping:
            type: attachment
            include_in_all: true
          indexing: ${Indexing.indexAsset(value)}

        'array<TYPO3\Media\Domain\Model\Asset>':
          elasticSearchMapping:
            type: attachment
            include_in_all: true
          indexing: ${Indexing.indexAsset(value)}
```

## Configurable Elasticsearch Mapping

(included in version >= 2.1)

If you want to fine-tune the indexing and mapping on a more detailed level, you can do so in the following way.

First, configure the index settings as you need them, e.g. configuring analyzers:

```
Flowpack:
  ElasticSearch:
    indexes:
      default:
        'typo3cr': # This index name must be the same as in the TYPO3.TYPO3CR.Search.elasticSearch.indexName setting
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

Then, you can change the analyzers on a per-field level; or e.g. reconfigure the _all field with the following snippet
in the NodeTypes.yaml. Generally this works by defining the global mapping at `[nodeType].search.elasticSearchMapping`:

```
'TYPO3.Neos:Node':
  search:
    elasticSearchMapping:
      _all:
        index_analyzer: custom_french_analyzer
        search_analyzer: custom_french_analyzer
```


## Debugging

In order to understand what's going on, the following commands are helpful:

* use `./flow nodeindex:showMapping` to show the currently defined Elasticsearch Mapping
* use the `.log()` statement inside queries to dump them to the Elasticsearch Log
* the logfile `Data/Logs/ElasticSearch.log` contains loads of helpful information.


## Version 2 vs Version 1

* Version 1 is the initial, productive version of the Neos Elasticsearch adapter.
* Version 2 has a dependency on TYPO3.TYPO3CR.Search; which contains base functionality
  which is also relevant for other search implementations (like the SQLite based SimpleSearch).

The configuration from Version 1 to Version 2 has changed; here's what to change:


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

