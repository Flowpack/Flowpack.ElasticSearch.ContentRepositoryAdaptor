# Needed Configuration in configuration.yml for Elasticsearch 1.4.x and 1.5.x

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
