# soluble/metadata

[![PHP Version](http://img.shields.io/badge/php-5.4+-ff69b4.svg)](https://packagist.org/packages/soluble/metadata)
[![HHVM Status](http://hhvm.h4cc.de/badge/soluble/metadata.png?style=flat)](http://hhvm.h4cc.de/package/soluble/metadata)
[![Build Status](https://travis-ci.org/belgattitude/soluble-metadata.png?branch=master)](https://travis-ci.org/belgattitude/soluble-metadata)
[![Code Coverage](https://scrutinizer-ci.com/g/belgattitude/soluble-metadata/badges/coverage.png?s=aaa552f6313a3a50145f0e87b252c84677c22aa9)](https://scrutinizer-ci.com/g/belgattitude/soluble-metadata)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/belgattitude/soluble-metadata/badges/quality-score.png?s=6f3ab91f916bf642f248e82c29857f94cb50bb33)](https://scrutinizer-ci.com/g/belgattitude/soluble-metadata)
[![Latest Stable Version](https://poser.pugx.org/soluble/metadata/v/stable.svg)](https://packagist.org/packages/soluble/metadata)
[![Total Downloads](https://poser.pugx.org/soluble/metadata/downloads.png)](https://packagist.org/packages/soluble/metadata)
[![License](https://poser.pugx.org/soluble/metadata/license.png)](https://packagist.org/packages/soluble/metadata)

## Introduction

The ultimate SQL query metadata reader.

## Features

- Retrieve metadata information from an SQL query (datatypes,...)
- Rely on native database driver information (does not parse the query)
- Fast, lightweight and thoroughly tested.

## Requirements

- PHP engine 5.4+, 7.0+ (HHVM does not work yet)
- Mysqli or PDO_mysql extension enabled (Mysqli highly recommended)

## Use cases

You can take advantage of soluble/metadata to format/render resulting query data 
according to their type (when displaying an html table for example), 
for basic validation (max lengths, decimals)...


## Notes

Currently metadata are read from the underlying database driver by executing a query 
with a limit 0 (almost no performance penalty). This ensure your query is always 
correctly parsed (even crazy ones) with almost no effort. 

The underlying driver methods `mysqli_stmt::result_metadata()`, `PDO::getColumnMeta()` 
used respectively by the metadata readers Mysql and PdoMysql are marked as experimental 
and subject to change on the PHP website. In practice, they haven't changed since 5.4 and
are stable. In case of a change in the php driver, it should be very easy to add a 
specific driver. No big deal.

Sadly there is some differences between PDO_mysql and mysqli in term of features. 
Generally the best is to use mysqli instead of pdo. PDO lacks some features like 
detection of autoincrement, enum, set, unsigned, grouped column and does not 
distinguish between table/column aliases and their original names. If you are
relying on those advanced features and willing to be portable between mysql extensions, 
have a look to alternatives like [phpmyadmin sql-parser](https://github.com/phpmyadmin/sql-parser).

Also if you are looking for a more advanced metadata reader (but limited to table - not a query),
have a look to the [soluble-schema](https://github.com/belgattitude/soluble-schema) project.

## Documentation

 - [Manual](http://docs.soluble.io/soluble-metadata/manual/) in progress and [API documentation](http://docs.soluble.io/soluble-metadata/api/) available.

## Installation

Instant installation via [composer](http://getcomposer.org/).

```console
$ php composer require soluble/metadata:^0.10
```
Most modern frameworks will include Composer out of the box, but ensure the following file is included:

```php
<?php
// include the Composer autoloader
require 'vendor/autoload.php';
```

## Quick start

### Getting metadata from an SQL query

```php
<?php

use Soluble\Metadata\Reader;

$conn = new \mysqli($hostname,$username,$password,$database);
$conn->set_charset($charset);

// Alternatively you can create a PDO_mysql connection
// $conn = new \PDO("mysql:host=$hostname", $username, $password, [
//            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
// ]);

$reader = new Reader\MysqliMetadataReader($conn);

$sql = "select id, name from my_table";

$meta = $reader->getColumnsMetadata($sql);

// The resulting ColumnsMetadata object look like

[
 ["id"] => <Column\Definition\IntegerColumn>
 ["name"] => <Column\Definition\StringColumn>
]

$col = $meta->getColumn('name');

echo $col->getOrdinalPosition();
echo $col->getTableName();
echo $col->getDatatype();
echo $col->getNativeDatatype();
echo $col->isPrimary() ? 'PK' : ''; 
echo $col->isNullable() ? 'nullable' : 'not null';
echo $col->getCharacterMaximumLength();
//...

foreach($meta as $column => $definition) {
    echo $definition->getName() . ', ' . $definition->getDatatype() . PHP_EOL;
}

```

## API

### AbstractMetadataReader

The `Soluble\Metadata\Reader\AbstractMetadataReader` offers

| Methods                      | Return        | Description                                         |
|------------------------------|---------------|-----------------------------------------------------|
| `getColumnsMetadata($sql)`   | `ColumnsMetadata` | Metadata information: ArrayObject with column name/alias   |


### ColumnsMetadata

The `Soluble\Metadata\ColumnsMetadata` allows to iterate over column information or return a specific column as
an `Soluble\Datatype\Column\Definition\AbstractColumnDefinition`.


| Methods                      | Return        | Description                                         |
|------------------------------|---------------|-----------------------------------------------------|
| `getColumn($name)`           | `AbstractColumnDefinition` | Information about a column             |


### AbstractColumnDefinition

Metadata information is stored as an `Soluble\Datatype\Column\Definition\AbstractColumnDefinition` object on which :


| General methods              | Return        | Description                                         |
|------------------------------|---------------|-----------------------------------------------------|
| `getName()`                  | `string`      | Return column name (unaliased)                      |
| `getAlias()`                 | `string`      | Return column alias                                 |
| `getTableName()`             | `string`      | Return origin table                                 |
| `getSchemaName()`            | `string`      | Originating schema for the column/table             |

| Type related methods         | Return        | Description                                         |
|------------------------------|---------------|-----------------------------------------------------|
| `getDataType()`              | `string`      | Column datatype (see Column\Type)                   |
| `getNativeDataType()`        | `string`      | Return native datatype                              |
| `isText()`                   | `boolean`     | Whether the column is textual (string, blog...)     |
| `isNumeric()`                | `boolean`     | Whether the column is numeric (decimal, int...)     |
| `isDate()`                   | `boolean`     | Is a date type                                      |

| Extra information methods    | Return        | Description                                         |
|------------------------------|---------------|-----------------------------------------------------|
| `isComputed()`               | `boolean`     | Whether the column is computed, i.e. '1+1, sum()    |
| `isGroup()`                  | `boolean`     | Grouped operation sum(), min(), max()               |


| Source infos                 | Return        | Description                                         |
|------------------------------|---------------|-----------------------------------------------------|
| `isPrimary()`                | `boolean`     | Whether the column is (part of) primary key         |
| `isNullable()`               | `boolean`     | Whether the column is nullable                      |
| `getColumnDefault()`         | `string`      | Return default value for column                     |
| `getOrdinalPosition()`       | `integer`     | Return position in the select                       |


Concrete implementations of `Soluble\Datatype\Column\Definition\AbstractColumnDefinition` are

| Drivers              | Interface                 | Description                   |
|----------------------|---------------------------|-------------------------------|
| `BitColumn`          |                           |                               |
| `BlobColumn`         |                           |                               |
| `BooleanColumn`      |                           |                               |
| `DateColumn`         | `DateColumnInterface`     |                               |
| `DateTimeColumn`     | `DatetimeColumnInterface` |                               |
| `DecimalColumn`      | `NumericColumnInterface`  |                               |
| `FloatColumn`        | `NumericColumnInterface`  |                               |
| `GeometryColumn`     |                           |                               |
| `IntegerColumn`      | `NumericColumnInterface`  |                               |
| `StringColumn`       | `TextColumnInterface`     |                               |
| `TimeColumn`         |                           |                               |



## Supported drivers

Currently only pdo_mysql and mysqli drivers  are supported. 

| Drivers            | Adapter interface implementation                     |
|--------------------|------------------------------------------------------|
| pdo_mysql          | `Soluble\DbAdapter\Adapter\MysqlAdapter`             |
| mysqli             | `Soluble\DbAdapter\Adapter\MysqlAdapter`             |


## Contributing

Contribution are welcome see [contribution guide](./CONTRIBUTING.md)

## Coding standards

* [PSR 4 Autoloader](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md)
* [PSR 2 Coding Style Guide](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md)
* [PSR 1 Coding Standards](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md)
* [PSR 0 Autoloading standards](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md)





