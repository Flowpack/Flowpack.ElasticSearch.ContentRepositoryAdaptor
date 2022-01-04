# Upgrade from Version 6 to Version 7

Version 7 is a major release which brings a lot of features and also some breaking changes. 

For a complete list of all changes see: [https://www.neos.io/blog/elasticsearch-package-releases.html](https://www.neos.io/blog/elasticsearch-package-releases.html)

### Renaming of the Internal field names, which now comply to Beats naming convention.

Neos-internal meta properties of nodes have previously been indexed with a leading underscore. Meta properties created by the Elasticsearch.ContentRepositoryAdapter with two leading underscores. 

Also Elasticsearch uses such underscore-prefixed fields for their internal meta data. That's why it is not possible to analyze these fields with Kibana, which makes it sometimes hard to use the tool to analyze and debug your index.

Now all internal properties are prefixed with the "neos_" prefix, and use snake_case complying with the beats naming convention.

Mapping table from old to new field names:

| Old | New |
|-----|--------|
| __identifier |neos_node_identifier |
| __parentPath |neos_parent_path |
| __path | neos_path |
| __typeAndSupertypes | neos_type_and_supertypes |
| __workspace | neos_workspace |
| _creationDateTime | neos_creationdate_time |
| _hidden | neos_hidden |
| _hiddenBeforeDateTime | neos_hidden_before_datetime |
| _hiddenAfterDateTime |neos_hidden_after_datetime |
| _hiddenInIndex | neos_hidden_in_index |
| _lastModificationDateTime |   neos_last_modification_datetime |
| _lastPublicationDateTime |neos_last_publication_datetime |
| __fulltextParts | neos_fulltext_parts |
| __fulltext |  neos_fulltext |
