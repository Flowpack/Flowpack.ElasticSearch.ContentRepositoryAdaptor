# Upgrade from Version 8 to Version 9

### Removed Eel-Helper

`Search.cacheLifetime()` has been removed, as this is not needed anymore.

### Renaming of the Internal field names

Some fields have been renamed or removed, due to changes in the content repository.

Mapping table from old to new field names:

| Old                         | New                 |
|-----------------------------|---------------------|
| neos_hidden_before_datetime | _removed_           | 
| neos_hidden_after_datetime  | _removed_           |
| neos_hidden_in_index        | neos_hidden_in_menu |
