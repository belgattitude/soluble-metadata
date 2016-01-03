# soluble/metadata

[![PHP Version](http://img.shields.io/badge/php-5.3+-ff69b4.svg)](https://packagist.org/packages/soluble/metadata)
[![HHVM Status](http://hhvm.h4cc.de/badge/soluble/metadata.png?style=flat)](http://hhvm.h4cc.de/package/soluble/metadata)
[![Build Status](https://travis-ci.org/belgattitude/soluble-metadata.png?branch=master)](https://travis-ci.org/belgattitude/soluble-metadata)
[![Code Coverage](https://scrutinizer-ci.com/g/belgattitude/soluble-metadata/badges/coverage.png?s=aaa552f6313a3a50145f0e87b252c84677c22aa9)](https://scrutinizer-ci.com/g/belgattitude/soluble-metadata)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/belgattitude/soluble-metadata/badges/quality-score.png?s=6f3ab91f916bf642f248e82c29857f94cb50bb33)](https://scrutinizer-ci.com/g/belgattitude/soluble-metadata)
[![Latest Stable Version](https://poser.pugx.org/soluble/metadata/v/stable.svg)](https://packagist.org/packages/soluble/metadata)
[![Total Downloads](https://poser.pugx.org/soluble/metadata/downloads.png)](https://packagist.org/packages/soluble/metadata)
[![License](https://poser.pugx.org/soluble/metadata/license.png)](https://packagist.org/packages/soluble/metadata)

## Introduction

Metadata from database queries

## Features

- 

## Requirements

- PHP engine 5.4+, 7.0+ or HHVM >= 3.2.
- PHP extensions pfo, pdo_mysql or  mysqli.

## Documentation

 - [Manual](http://docs.soluble.io/soluble-metadata/manual/) in progress and [API documentation](http://docs.soluble.io/soluble-metadata/api/) available.

## Installation

Instant installation via [composer](http://getcomposer.org/).

```console
php composer require soluble/metadata:0.*
```
Most modern frameworks will include Composer out of the box, but ensure the following file is included:

```php
<?php
// include the Composer autoloader
require 'vendor/autoload.php';
```

## Quick start

### Connection

Create an adapter from an existing PDO connection

```php
<?php

use Soluble\DbWrapper;

$conn = new \PDO("mysql:host=$hostname", $username, $password, [
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
]);

try {
    $adapter = DbWrapperAdapterFactory::createFromConnection($conn);
} catch (DbWrapper\Exception\InvalidArgumentException $e) {
    // ...
}

```



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





