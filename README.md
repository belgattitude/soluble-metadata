# soluble/metadata

[![PHP Version](http://img.shields.io/badge/php-5.4+-ff69b4.svg)](https://packagist.org/packages/soluble/metadata)
[![Build Status](https://travis-ci.org/belgattitude/soluble-metadata.svg?branch=master)](https://travis-ci.org/belgattitude/soluble-metadata)
[![Code Coverage](https://scrutinizer-ci.com/g/belgattitude/soluble-metadata/badges/coverage.png?s=aaa552f6313a3a50145f0e87b252c84677c22aa9)](https://scrutinizer-ci.com/g/belgattitude/soluble-metadata)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/belgattitude/soluble-metadata/badges/quality-score.png?s=6f3ab91f916bf642f248e82c29857f94cb50bb33)](https://scrutinizer-ci.com/g/belgattitude/soluble-metadata)
[![Latest Stable Version](https://poser.pugx.org/soluble/metadata/v/stable.svg)](https://packagist.org/packages/soluble/metadata)
[![Total Downloads](https://poser.pugx.org/soluble/metadata/downloads.png)](https://packagist.org/packages/soluble/metadata)
[![License](https://poser.pugx.org/soluble/metadata/license.png)](https://packagist.org/packages/soluble/metadata)
[![HHVM Status](http://hhvm.h4cc.de/badge/soluble/metadata.svg)](http://hhvm.h4cc.de/package/soluble/metadata)

## Introduction

`soluble-metadata` is a *low level* library *currently focusing on MySQL* which extracts metadata from an sql query with extensibility, speed and portability in mind.

## Use cases

You can take advantage of soluble/metadata to format/render resulting query data 
according to their type (when rendering an html table, generating an excel sheet...), 
for basic validation (max lengths, decimals)...

## Features

- Extract metadata information from an SQL query (datatypes,...)
- Common API across various driver implementations.
- Rely on native database driver information (does not parse the query in PHP)
- Works even when the query does not return results (empty resultset).
- Carefully tested with different implementations (libmariadb, mysqlnd, libmysql, pdo_mysql).

> Under the hood, the metadata extraction relies on the driver methods `mysqli_stmt::result_metadata()` and `PDO::getColumnMeta()`.
> Although the `soluble-metadata` API unify their usage and type detection, differences still exists for more advanced features. 
> A specific effort has been made in the documentation to distinguish possible portability issues when switching from one driver to another.
> Keep that in mind when using it.

## Requirements

- PHP engine 5.4+, 7.0+, 7.1+ *(HHVM does not work yet)*
- Mysqli or PDO_mysql extension enabled *(Mysqli exposes more features)*

## Documentation

 - This README and [API documentation](http://docs.soluble.io/soluble-metadata/api/) available.

## Installation

Instant installation via [composer](http://getcomposer.org/).

```console
$ composer require soluble/metadata
```

Most modern frameworks will include composer out of the box, but ensure the following file is included:

```php
<?php
// include the Composer autoloader
require 'vendor/autoload.php';
```

## Basic example

```php
<?php

use Soluble\Metadata\Reader;
use Soluble\Datatype\Column\Type as DataType;


$conn = new \mysqli($hostname,$username,$password,$database);
$conn->set_charset($charset);

$metaReader = new Reader\MysqliMetadataReader($conn);

$sql = "select * from `my_table`";

try {
    $md = $metaReader->getColumnsMetadata($sql);
} catch (\Soluble\Metadata\Exception\InvalidQueryException $e) {
    // ...
}

foreach($md as $column_name => $col_def) {
   
   $datatype = $col_def->getDatatype();
   
   echo $column_name . "\t" . $datatype . "\t";
   
   echo ($col_def->isNullable() ? 'Y' : 'N') . '\t';
   
   switch ($datatype) {
       case DataType::TYPE_STRING:  // equivalent to 'string'
           echo $col_def->getCharacterOctetLength() . "\t";
           break;
       case DataType::TYPE_DECIMAL:
           echo $col->getNumericPrecision() . "\t";  // For DECIMAL(5,2) -> precision = 5 
           echo $col->getNumericScale() . "\t";      // For DECIMAL(5,2) -> scale = 2
           break;
           
       // ...see the doc for more possibilitities
   }
   
   echo $col_def->getNativeType() . PHP_EOL;
}  

```

Could print something like :

| Column name   | Type             | Null | Length | Precision | Scale | Native        |
|---------------|------------------|-----:|-------:|----------:|------:|---------------|
| column_1      | integer          | N    |        |           |       | BIGINT        |
| column_2      | string           | N    | 255    |           |       | VARCHAR       |
| column_3      | decimal          | Y    |        | 5         | 2     | DECIMAL       |
| column_4      | datetime         | Y    |        |           |       | DATETIME      |
| column_5      | date             | Y    |        |           |       | DATE          |
| column_6      | time             | Y    |        |           |       | TIME          |
| column_7      | float            | N    |        |           |       | FLOAT         |
| column_8      | blob             | Y    | 16777215 |           |       | MEDIUMBLOB    |
| column_9      | spatial_geometry | Y    |        |           |       | null *(N/A)*  |
| column_10     | null             | Y    |        |           |       | null *(N/A)*  |

...

## Usage

### Step 1. Initiate a metadata reader

For **Mysqli** use the `MysqlMetadataReader` :

```php
<?php
use Soluble\Metadata\Reader;

$conn = new \mysqli($hostname,$username,$password,$database);
$conn->set_charset($charset);

$reader = new Reader\MysqliMetadataReader($conn);

``` 

For **Pdo_mysql** use the `PdoMysqlReader` :

```php
<?php
use Soluble\Metadata\Reader;

$conn = new \PDO("mysql:host=$hostname", $username, $password, [
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
]);

$reader = new Reader\PDOMysqlMetadataReader($conn);

```

### Step 2. Query metadata extraction

```php
<?php

//....

$reader = new Reader\MysqliMetadataReader($conn);

$sql = "
         SELECT `p`.`post_id`,
                `p`.`title` AS `post_title` 
                `p`.`created_at`,
                'constant' AS `constant_col`,
                1 + 2 AS `computed_col`, 
                null as `null_col`
                COUNT(`c`.*) as `nb_comments`,
                MAX(`c`.`created_at`) as latest_comment
                 
            FROM `post` AS `p`
            LEFT OUTER JOIN `comment` as `c`  
                 ON `c`.`post_id` = `p`.`post_id`
            GROUP BY `p`.`post_id`, `p`.`title`, 
                     `p`.`created_at`, `constant_col`, 
                     `computed_col`, `null_col`     
       ";


try {
    $meta = $reader->getColumnsMetadata($sql);
} catch (\Soluble\Metadata\Exception\InvalidQueryException $e) { 
    //...
}

/*
  The resulting ColumnsMetadata will contain something like:

  [
     "post_id"        => '<Soluble\Datatype\Column\Definition\IntegerColumn>',
     "post_title"     => '<Soluble\Datatype\Column\Definition\StringColumn>',
     "created_at"     => '<Soluble\Datatype\Column\Definition\DatetimeColumn>',
     "constant_col"   => '<Soluble\Datatype\Column\Definition\StringColumn>',
     "computed_col"   => '<Soluble\Datatype\Column\Definition\IntegerColumn>',
     "null_col"       => '<Soluble\Datatype\Column\Definition\NullColumn>',
     "nb_comments"    => '<Soluble\Datatype\Column\Definition\IntegerColumn>',
     "latest_comment" => '<Soluble\Datatype\Column\Definition\DateTimeColumn>'
     
  ]
    
*/  

```

### Step 3: Getting column type (4 options)


```php
<?php

// ...

$meta = $reader->getColumnsMetadata($sql);

// Retrieve a specific column (i.e. 'post_title')
// Note the parameter is the column alias if defined 

$col = $meta->getColumn('post_title'); 

                                       
// Type detection
// ----------------------

// Option 1, type detection by datatype name
// ------------------------------------------

echo $col->getDatatype(); // -> 'string' (Soluble\Datatype\Column\Type::TYPE_STRING)  

/* 
   The normalized datatypes are defined in the 
   Soluble\Datatype\Column\Type::TYPE_(*) and can be :
   'string', 'integer', 'decimal', 'float', 'boolean', 
   'datetime', 'date', 'time', 'bit', 'spatial_geometry'
*/


// Option 2, type detection by classname
// --------------------------------------

if ($col instanceof \Soluble\Datatype\Column\IntegerColumn) {
    // ... could be also BitColumn, BlobColumn, BooleanColumn
    // ... DateColumn, DateTimeColumn, DecimalColumn, FloatColumn
    // ... GeometryColumn, IntegerColumn, StringColumn, TimeColumn,
    // ... NullColumn
}

// Option 3, type detection by interface (more generic)
// -----------------------------------------------------

if ($col instanceof \Soluble\Datatype\Column\NumericColumnInterface) {
   // ... for example NumericColumnInterface 
   // ... includes DecimalColumn, FloatColumn, IntegerColumn
}

// Option 4, type detection by helper functions (more generic)
// -----------------------------------------------------------

$col->isText();     // Whether the column contains text (CHAR, VARCHAR, ENUM...)
$col->isNumeric();  // Whether the column is numeric (INT, DECIMAL, FLOAT...)
$col->isDatetime(); // Whether the column is a datetime (DATETIME)
$col->isDate();     // Whther the column is a date (DATE)

```


### Step 4: Getting datatype extra information  

The following methods are supported and portable between `mysqli` and `PDO_mysql` drivers:

```php
<?php

// ...

// For all types
// -------------

echo $col->getOrdinalPosition(); // -> 2 (column position in the query)
echo $col->isNullable() ? 'nullable' : 'not null';
echo $col->isPrimary() ? 'PK' : '';  // Many columns may have the primary flag
                                     // The meaning of it depends on your query

// For decimal based types
// -----------------------

echo $col->getNumericPrecision(); // For DECIMAL(5,2) -> 5 is the precision
echo $col->getNumericScale();     // For DECIMAL(5,2) -> 2 is the scale
echo $col->isNumericUnsigned();   // Whether the numeric value is unsigned.

// For character/blob based types
// ------------------------------

echo $col->getCharacterOctetLength();  // Octet length (in multibyte context length might differs)
 
```


### Getting column specifications.

The following methods are also portable.  
 
 
```php 
<?php

// ...

echo $col->getAlias(); // Column alias name -> "post_title" (or column name if not aliased)

echo $col->isComputed(); // Whenever there's no table linked (for GROUP, constants, expression...)

echo $col->getTableAlias(); // Originating table alias -> "p" (or table name if not aliased)
                            // If empty, the column is computed (constant, group,...)
```

> **The methods used in the example below gives different results with `pdo_mysql` and `mysqli` drivers.
> Use them with care if portability is required !!!**

```php
<?php

// ...

echo $col->getTableName();  // Originating table -> "post"
                            // (*) PDO_mysql always return the table alias if aliased 

echo $col->getName();  // Column original name -> "title". 
                       // (*) PDO_mysql always return the alias if aliased

echo $col->getNativeType(); // Return the column definition native type
                            // i.e: BIGINT, SMALLINT, VARCHAR, ENUM
                            // (*) PDO_mysql consider 
                            //        - ENUM, SET and VARCHAR as CHAR
                            
echo $col->isGroup(); // Whenever the column is part of a group (MIN, MAX, AVG,...)
                      // (*) PDO_mysql is not able to retrieve group information
                      // (*) Mysqli: detection of group is linked to the internal driver
                      //     Check your driver with mysqli_get_client_version().
                      //       - mysqlnd detects:
                      //          - COUNT, MIN, MAX
                      //       - libmysql detects:
                      //          - COUNT, MIN, MAX, AVG, GROUP_CONCAT
                      //       - libmariadb detects:
                      //          - COUNT, MIN, MAX, AVG, GROUP_CONCAT and growing    


// For numeric types
// -----------------

echo $col->isAutoIncrement();   // Only make sense for primary keys.
                                // (*) Unsupported with PDO_mysql

echo $col->isNumericUnsigned(); // Whether the numeric value is unsigned.
                                // (*) Unsupported with PDO_mysql
```


### Unsupported methods

Those methods are still unsupported on both mysqli and PDO_mysql implementations but kept as reference

```php
<?php

// ... 

echo $col->getColumnDefault(); // Always return null
echo $col->getCharacterMaximumLength();  // Returns $col->getCharacterOctetLength()
                                         // and does not (yet) handle multibyte aspect.

```


## API

### AbstractMetadataReader

Use the `Reader\AbstractMetadataReader::getColumnsMetadata($sql)` to extract query metadata.

```php
<?php

use Soluble\Metadata\Reader;
use PDO;

$conn = new PDO("mysql:host=$hostname", $username, $password, [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
]);

$reader = new Reader\PdoMysqlMetadataReader($conn);

$sql = "select id, name from my_table";

$columnsMeta = $reader->getColumnsMetadata($sql);

```

| Methods                      | Return            | Description                                                |
|------------------------------|-------------------|------------------------------------------------------------|
| `getColumnsMetadata($sql)`   | `ColumnsMetadata` | Metadata information: ArrayObject with column name/alias   |


### ColumnsMetadata

The `Soluble\Metadata\ColumnsMetadata` allows to iterate over column information or return a specific column as
an `Soluble\Datatype\Column\Definition\AbstractColumnDefinition`.

```php
<?php

$reader = new Reader\PdoMysqlMetadataReader($conn);

$sql = "select id, name from my_table";

$columnsMeta = $reader->getColumnsMetadata($sql);

foreach ($columnsMeta as $col_name => $col_def) {
    echo $coldev->getDatatype() . PHP_EOL; 
}

$col = $columnsMeta->getColumn('id');
echo $col->getDatatype();

```

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
| `getOrdinalPosition()`       | `integer`     | Return position in the select                       |

| Type related methods         | Return        | Description                                         |
|------------------------------|---------------|-----------------------------------------------------|
| `getDataType()`              | `string`      | Column datatype (see Column\Type)                   |
| `getNativeDataType()`        | `string`      | Return native datatype (VARCHAR, BIGINT...)         |
| `isText()`                   | `boolean`     | Whether the column is textual (string, blog...)     |
| `isNumeric()`                | `boolean`     | Whether the column is numeric (decimal, int...)     |
| `isDatetime()`               | `boolean`     | Is a datetime type                                  |
| `isDate()`                   | `boolean`     | Is a date type                                      |

| Flags information            | Return        | Description                                         |
|------------------------------|---------------|-----------------------------------------------------|
| `isPrimary()`                | `boolean`     | Whether the column is (part of) primary key         |
| `isAutoIncrement()`          | `boolean`     | If it's an autoincrement column (only mysqli)       |
| `isNullable()`               | `boolean`     | Whether the column is nullable                      |
| `getColumnDefault()`         | `string`      | Return default value for column (not working yet)   |


| Extra information methods    | Return        | Description                                         |
|------------------------------|---------------|-----------------------------------------------------|
| `isComputed()`               | `boolean`     | Whether the column is computed, i.e. '1+1, sum()    |
| `isGroup()`                  | `boolean`     | Grouped operation sum(), min(), max()               |

| Numeric type specific        | Return        | Description                                         |
|------------------------------|---------------|-----------------------------------------------------|
| `getNumericScale()`          | `integer`     | Scale for numbers, i.e DECIMAL(10,2) -> 10          |
| `getNumericPrecision()`      | `integer`     | Precision, i.e. DECIMAL(10,2) -> 2                  |
| `isNumericUnsigned()`        | `boolean`     | Whether signed or unsigned                          |

| Character type specific       | Return        | Description                                                |
|-------------------------------|---------------|------------------------------------------------------------|
| `getCharacterMaximumLength()` | `integer`     | Max string length for chars (unicode sensitive)            |
| `getCharacterOctetLength()`   | `integer`     | Max octet length for chars, blobs... (binary, no unicode)  |



### AbstractColumnDefinition implementations

Here's the list of concrete implementations for `Soluble\Datatype\Column\Definition\AbstractColumnDefinition`.

They can be used as an alternative way to check column datatype. For example

```php
use Soluble\Datatype\Column\Definition;

if ($coldef instanceof Definition\DateColumnInterface) {

    // equivalent to
    // if ($coldef->isDate()) {

    $date = new \DateTime($value);
    echo $value->format('Y');
} elseif ($coldef instanceof Definition\NumericColumnInterface) {
    echo number_format($value, $coldef->getNumericPrecision);
}
```

| Definition Type      | Interface                 | Description                   |
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
| `NullColumn`         |                           | Special case for columns aliasing 'NULL' value  |


## Supported readers

Currently only pdo_mysql and mysqli drivers  are supported. 

| Drivers            | Reader implementation                                |
|--------------------|------------------------------------------------------|
| pdo_mysql          | `Soluble\Metadata\Reader\PdoMysqlMetadataReader`     |
| mysqli             | `Soluble\Metadata\Reader\MysqliMetadataReader`       |


## Future ideas

- Implement more drivers (pgsql...), contributions welcome !!!

## Contributing

Contribution are welcome see [contribution guide](./CONTRIBUTING.md)

## Notes

Currently metadata are read from the underlying database driver by executing a query 
with a limit 0 (almost no performance penalty). This ensure your query is always 
correctly parsed (even crazy ones) with almost no effort. 

The underlying driver methods `mysqli_stmt::result_metadata()`, `PDO::getColumnMeta()` 
used respectively by the metadata readers Mysql and PdoMysql are marked as experimental 
and subject to change on the PHP website. In practice, they haven't changed since 5.4 and
are stable. In case of a change in the php driver, it should be very easy to add a 
specific driver. 

Sadly there is some differences between PDO_mysql and mysqli in term of features. 
Generally the best is to use mysqli instead of pdo. PDO lacks some features like 
detection of autoincrement, enum, set, unsigned, grouped column and does not 
distinguish between table/column aliases and their original table/column names. 

If you want to rely on this specific feature (aliases) have a look to alternatives like [phpmyadmin sql-parser](https://github.com/phpmyadmin/sql-parser).

Also if you are looking for a more advanced metadata reader (but limited to table - not a query),
have a look to the [soluble-schema](https://github.com/belgattitude/soluble-schema) project which share
the same datatype standards while exposing more information like foreign keys,... in a more portable way. 

## Coding standards

* [PSR 4 Autoloader](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md)
* [PSR 2 Coding Style Guide](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md)
* [PSR 1 Coding Standards](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md)

