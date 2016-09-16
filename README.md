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

## Use cases

You can take advantage of soluble/metadata to format/render resulting query data 
according to their type (when displaying an html table for example), 
for basic validation (max lengths, decimals)...

## Features

- Retrieve metadata information from an SQL query (datatypes,...)
- Rely on native database driver information (does not parse the query)
- Provides an unified API, fast, lightweight and thoroughly tested.
- Attempt to be portable (at least to the internal driver possibilities)
- MIT opensource license

## Requirements

- PHP engine 5.4+, 7.0+ (HHVM does not work yet)
- Mysqli or PDO_mysql extension enabled (Mysqli highly recommended)

## Documentation

 - [Manual](http://docs.soluble.io/soluble-metadata/manual/) in progress and [API documentation](http://docs.soluble.io/soluble-metadata/api/) available.

## Installation

Instant installation via [composer](http://getcomposer.org/).

```console
$ php composer require soluble/metadata
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

// Step 1. Get the connection object
// ---------------------------------

$conn = new \mysqli($hostname,$username,$password,$database);
$conn->set_charset($charset);

/* 
  Alternatively you can create a PDO_mysql connection
  $conn = new \PDO("mysql:host=$hostname", $username, $password, [
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
  ]);
  and replace the reader by Reader\PdoMysqlMetadataReader($conn)
*/

// Step 2. Initiate the corresponding MetadataReader
// -------------------------------------------------

$reader = new Reader\MysqliMetadataReader($conn);

// Step 3. Write a query
// ---------------------

$sql = "
         SELECT `p`.`post_id`,
                `p`.`title` AS `post_title` 
                `p`.`created_at`,
                'constant' AS `constant_col`,
                1 + 2 AS `computed_col`, 
                COUNT(`c`.*) as `nb_comments`
                 
            FROM `post` AS `p`
            LEFT OUTER JOIN `comment` as `c`  
                 ON `c`.`post_id` = `p`.`post_id`
       ";


// Step 4: Read the metadata
// -------------------------

$meta = $reader->getColumnsMetadata($sql);

/*
  The resulting ColumnsMetadata will contain something like:

  [
     "post_id"      => '<Soluble\Datatype\Column\Definition\IntegerColumn>',
     "post_title"   => '<Soluble\Datatype\Column\Definition\StringColumn>',
     "created_at"   => '<Soluble\Datatype\Column\Definition\DatetimeColumn>',
     "constant_col" => '<Soluble\Datatype\Column\Definition\StringColumn>',
     "computed_col" => '<Soluble\Datatype\Column\Definition\IntegerColumn>',
     "nb_comments"  => '<Soluble\Datatype\Column\Definition\IntegerColumn>'
  ]
  
  You can iterate it :
  
  foreach($meta as $column => $definition) {
        echo $definition->getName() . ', ' . $definition->getDatatype() . PHP_EOL;
  }  
  
*/  

// Step 5: Retrieve a specific column (i.e. 'post_title')
// ------------------------------------------------------

$col = $meta->getColumn('post_title');


// Step 6: Type detection
// ----------------------

// 6.1: Option 1, type detection by datatype name
// -----------------------------------------------

echo $col->getDatatype(); // -> 'string' (Soluble\Datatype\Column\Type::TYPE_STRING)  

/* 
   The normalized datatypes are defined in the 
   Soluble\Datatype\Column\Type::TYPE_(*) and can be :
   'string', 'integer', 'decimal', 'float', 'boolean', 
   'datetime', 'date', 'time', 'bit', 'spatial_geometry'
*/


// 6.2 Option 2, type detection by classname
// -----------------------------------------

if ($col instanceof \Soluble\Datatype\Column\IntegerColumn) {
    // ... could be also BitColumn, BlobColumn, BooleanColumn
    // ... DateColumn, DateTimeColumn, DecimalColumn, FloatColumn
    // ... GometryColumn, IntegerColumn, StringColumn, TimeColumns
}

// 6.3 Option 3, type detection by interface (more generic)
// --------------------------------------------------------

if ($col instanceof \Soluble\Datatype\Column\NumericColumnInterface) {
   // ... for example NumericColumnInterface 
   // ... includes DecimalColumn, FloatColumn, IntegerColumn
}

// 6.4 Option 4, type detection by helper functions
// -------------------------------------------------

$col->isText();     // Whether the column contains text (CHAR, VARCHAR, ENUM...)
$col->isNumeric();  // Whether the column is numeric (INT, DECIMAL, FLOAT...)
$col->isDatetime(); // Whether the column is a datetime (DATETIME)
$col->isDate();     // Whther the column is a date (DATE)


// Step 7: Retrieve datatype information
// -------------------------------------

// Step 7.1: For all types
// -----------------------

echo $col->getOrdinalPosition(); // -> 2 (column position)
echo $col->isNullable() ? 'nullable' : 'not null';
echo $col->isPrimary() ? 'PK' : '';  // Many columns may have the primary flag
                                     // The meaning of it depends on your query


// Step 7.2: For decimal based types
// --------------------------------

echo $col->getNumericPrecision(); // For DECIMAL(5,2) -> 5 is the precision
echo $col->getNumericScale();     // For DECIMAL(5,2) -> 2 is the scale
echo $col->isNumericUnsigned();   // Whether the numeric value is unsigned.

// Step 7.3: For character/blob based types
// ----------------------------------------

echo $col->getCharacterOctetLength();  // Octet length (in multibyte context length might differs)
 
// Step 8: Ask for extended information 
// ------------------------------------

// #############################################################
// # WARNING !!!                                               #
// #  Methods with an asterisk shows differences between       #
// #  PDO_mysql and mysqli implementations.                    #
// #                                                           #
// #  If portability is required, avoid relying on them        # 
// #  See also differences between mysqli and pdo_mysql.       #
// #############################################################
  
// Step 8.1: Extra column information
// ----------------------------------

echo $col->getAlias(); // Column alias name -> "post_title" (or column name if not aliased)
 
echo $col->getName();  // Column original name -> "title". 
                       // (*) PDO_mysql always return the alias

echo $col->getNativeType(); // Return the column definition native type
                            // i.e: BIGINT, SMALLINT, VARCHAR, ENUM
                            // (*) PDO_mysql consider 
                            //        - ENUM, SET and VARCHAR as CHAR
                            

echo $col->isAutoIncrement();   // Only make sense for primary keys.
                                // (*) Unsupported with PDO_mysql

echo $col->isNumericUnsigned(); // Whether the numeric value is unsigned.
                                // (*) Unsupported with PDO_mysql

echo $col->isComputed(); // Whenever there's no table linked (for GROUP, constants, expression...)

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


// Step 8.2: Extra table information
// ---------------------------------

echo $col->getTableAlias(); // Originating table alias -> "p" (or table name if not aliased)
                            // If empty, the column is computed (constant, group,...)
                            
echo $col->getTableName();  // Originating table -> "post"
                            // (*) PDO_mysql always return the table alias 


// Step 8.3: Unsupported both with mysqli / pdo_mysql
// --------------------------------------------------

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


## Supported readers

Currently only pdo_mysql and mysqli drivers  are supported. 

| Drivers            | Reader implementation                                |
|--------------------|------------------------------------------------------|
| pdo_mysql          | `Soluble\Metadata\Reader\PdoMysqlMetadataReader`     |
| mysqli             | `Soluble\Metadata\Reader\MysqliMetadataReader`       |


## Future ideas

- Implement more drivers (pgsql...)
- Implement a pure php reader (on top of [phpmyadmin sql-parser](https://github.com/phpmyadmin/sql-parser))

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
specific driver. No big deal.

Sadly there is some differences between PDO_mysql and mysqli in term of features. 
Generally the best is to use mysqli instead of pdo. PDO lacks some features like 
detection of autoincrement, enum, set, unsigned, grouped column and does not 
distinguish between table/column aliases and their original table/column names. 

If you want to rely on this specific feature (aliases) have a look to alternatives like [phpmyadmin sql-parser](https://github.com/phpmyadmin/sql-parser).

Also if you are looking for a more advanced metadata reader (but limited to table - not a query),
have a look to the [soluble-schema](https://github.com/belgattitude/soluble-schema) project which share
the same datatypes structure with more informations.

## Coding standards

* [PSR 4 Autoloader](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md)
* [PSR 2 Coding Style Guide](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md)
* [PSR 1 Coding Standards](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md)
* [PSR 0 Autoloading standards](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md)





