# Needed Configuration in configuration.yml for Elasticsearch 1.6.x and 1.7.x

In version 1.6 the `script.disable_dynamic` settings and the Groovy sandbox as such [have been deprecated]
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
