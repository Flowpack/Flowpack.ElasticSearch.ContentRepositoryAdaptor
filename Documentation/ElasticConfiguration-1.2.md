# Needed Configuration for Elasticsearch 1.2.x


If you are using Elasticsearch version 1.2 you have also to install groovy as a plugin. To install the plugin just run
the following command in the root folder of your elastic:

```
bin/plugin -install elasticsearch/elasticsearch-lang-groovy/2.2.0.
```

```
script.disable_dynamic: false
script.default_lang: groovy

```
