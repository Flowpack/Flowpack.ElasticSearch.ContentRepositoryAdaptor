# Mapping for Elasticsearch 5.x

In contrast to earlier versions, Elasticsearch has deprecated the `string` type
in version 5. This affects the `index` setting on a field as well.

This package tries to adjust the mapping on-the-fly so you may not need to
adjust anything, but in certain cases knowing what would be correct might help.

## `string` is dead, long live strings

The `string` type was used for different things, namely analyzed content to
be searched as fulltext, as well as in a non-analyzed way for keyword searches.

Thus `string` has been split into the new types `text` and `keyword`. Here is a
"conversion table":

| 2.x                                       | 5.x                              |
|-------------------------------------------|----------------------------------|
| "type": "string", "index": "no"           | "type": "text", "index": false   |
| "type": "string"[, "index": "analyzed"]   | "type": "text", "index": true    |
| "type": "string", "index": "not_analyzed" | "type": "keyword", "index": true |

## Conflicting field types

Something that has been enforced since version 2 of Elasticsearch is the fact
that fields of the same name must have the same type within an index, across
types. See https://www.elastic.co/blog/great-mapping-refactoring#conflicting-mappings.

With the split of `string` into two types, new conflicts of that type may arise.

To fix those conflicts, you may need to rename properties or adjust the mapping
in your node types.
