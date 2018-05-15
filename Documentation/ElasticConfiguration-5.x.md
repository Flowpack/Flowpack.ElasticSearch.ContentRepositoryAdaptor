# Needed Configuration in configuration.yml for Elasticsearch 5.x

No special configuration is needed for Elasticsearch 5.x. Nice, isn't it?

But the following can make your life easier:

```
# the following settings secure your cluster
cluster.name: [PUT_YOUR_CUSTOM_NAME_HERE]
node.name: [PUT_YOUR_CUSTOM_NAME_HERE]
network.host: 127.0.0.1
```

**Note:** When using Elasticsearch 5.x changes to the mapping may be needed.
More information on the [mapping in ElasticSearch 5.x](Documentation/ElasticMapping-5.x.md).
