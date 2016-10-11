# Needed Configuration in configuration.yml for Elasticsearch 2.0.x and 2.4.x

```
# The following settings are absolutely required for the CR adaptor to work
script.engine.groovy.inline.update: true

# the following settings are well-suited for smaller Elasticsearch instances (i.e. as long as you can stay on one host)
index.number_of_shards: 1
index.number_of_replicas: 0
```

# Java Security Manager Policy file `.java.policy`

We need to allow access to some classes in the groovy script, so we need to modify (or create) the `~/.java.policy` for 
the user that is running Elasticsearch (`elasticsearch` by default). See [the Elasticsearch Reference](https://www.elastic.co/guide/en/elasticsearch/reference/2.3/modules-scripting-security.html#_customising_the_classloader_whitelist) for more info.

```
grant {
    permission org.elasticsearch.script.ClassPermission "java.util.*";
};
```
