# CHANGELOG

## 1.2.0 (2018-01-18) PHP 7.1 - no BC break 

### Changed

- Updated for PHP7.1 strict types checks (no bc-break)

## 1.1.0 (2017-08-12)

### New feature

- New helper method `getTableMetadata(string $table)` to automatically get metadata from a table. 

### Fix
- `InvalidQueryException` was not thrown by the pdo mysql driver. 

## 1.0.2 (2017-08-12)

### Improvements

- More strict comparison checks
- Some cs-fixes

## 1.0.1 (2016-10-12)

### Improvements

- Added detection on TINY_BLOB, LONG_BLOB, MEDIUM_BLOB for PDO_mysql

## 1.0.0 (2016-10-12)

### Improvements

- Tested on PHP 7.1
- Documenting InvalidQueryException
- Minor perf fixes and internal code cleanup

## 0.10.4 (2016-09-22)

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

  