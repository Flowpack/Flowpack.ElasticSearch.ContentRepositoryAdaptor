# Needed Configuration in configuration.yml for Elasticsearch 2.x

Since version 2.0 the fine-grained script settings are in place as described in the scripting docs
(https://www.elastic.co/guide/en/elasticsearch/reference/2.4/modules-scripting.html#enable-dynamic-scripting).

```
# The following settings are absolutely required for the CR adaptor to work
script.inline: true

# the following settings secure your cluster
cluster.name: [PUT_YOUR_CUSTOM_NAME_HERE]
network.host: 127.0.0.1

# the following settings are well-suited for smaller Elasticsearch instances (e.g. as long as you can stay on one host)
index.number_of_shards: 1
index.number_of_replicas: 0
```
