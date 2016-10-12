# CHANGELOG

### 0.10.x (xxxx-xx-xx)


### 1.0.0 (2016-10-12)

    - Tested on PHP 7.1
    - Documenting InvalidQueryException
    - Minor perf fixes and internal code cleanup

### 0.10.4 (2016-09-22)

    - Support for null column datatype, for example
    
        ```sql
            select null as my_alias; 
        ```
        
      In this case, the column type cannot be determined and will render a NullColumn type definition  

### 0.10.3 (2016-09-22)

    - Added UnsupportedTypeException whenever a fied type cannot be resolved.

### 0.9.6 (2016-05-14)

    - Updated README.md
    - Added method `getColumn($name)` on `ColumnsMetadata`.
    - More tests for differences between pdo_mysql and mysqli

  