<?php

declare(strict_types=1);

namespace Soluble\Metadata\Reader\Mapping;

use Soluble\Datatype\Column;
use ArrayObject;

class PdoMysqlMapping
{
    public static function getDatatypeMapping(): ArrayObject
    {
        $mapping = new ArrayObject([
            'STRING' => ['type' => Column\Type::TYPE_STRING, 'native' => 'CHAR'],
            'VAR_STRING' => ['type' => Column\Type::TYPE_STRING, 'native' => 'VARCHAR'],
            // BLOBS ARE CURRENTLY SENT AS TEXT
            // I DIDN'T FIND THE WAY TO MAKE THE DIFFERENCE !!!
            'BLOB' => ['type' => Column\Type::TYPE_BLOB, 'native' => 'BLOB'],
            'TINY_BLOB' => ['type' => Column\Type::TYPE_BLOB, 'native' => 'TINY_BLOB'],
            'MEDIUM_BLOB' => ['type' => Column\Type::TYPE_BLOB, 'native' => 'MEDIUM_BLOB'],
            'LONG_BLOB' => ['type' => Column\Type::TYPE_BLOB, 'native' => 'LONG_BLOB'],

            // integer
            'TINY' => ['type' => Column\Type::TYPE_INTEGER, 'native' => 'TINYINT'],
            'SHORT' => ['type' => Column\Type::TYPE_INTEGER, 'native' => 'SMALLINT'],
            'INT24' => ['type' => Column\Type::TYPE_INTEGER, 'native' => 'MEDIUMINT'],
            'LONG' => ['type' => Column\Type::TYPE_INTEGER, 'native' => 'INTEGER'],
            'LONGLONG' => ['type' => Column\Type::TYPE_INTEGER, 'native' => 'BIGINT'],

            // timestamps
            'TIMESTAMP' => ['type' => Column\Type::TYPE_DATETIME, 'native' => 'TIMESTAMP'],
            'DATETIME' => ['type' => Column\Type::TYPE_DATETIME, 'native' => 'DATETIME'],
            // dates
            'DATE' => ['type' => Column\Type::TYPE_DATE, 'native' => 'DATE'],
            'NEWDATE' => ['type' => Column\Type::TYPE_DATE, 'native' => 'DATE'],
            // time
            'TIME' => ['type' => Column\Type::TYPE_TIME, 'native' => 'TIME'],
            // decimals
            'DECIMAL' => ['type' => Column\Type::TYPE_DECIMAL, 'native' => 'DECIMAL'],
            'NEWDECIMAL' => ['type' => Column\Type::TYPE_DECIMAL, 'native' => 'DECIMAL'],
            'FLOAT' => ['type' => Column\Type::TYPE_FLOAT, 'native' => 'FLOAT'],
            'DOUBLE' => ['type' => Column\Type::TYPE_FLOAT, 'native' => 'DOUBLE'],
            // boolean
            'BIT' => ['type' => Column\Type::TYPE_BIT, 'native' => 'BIT'],
            'BOOLEAN' => ['type' => Column\Type::TYPE_BOOLEAN, 'native' => 'BOOLEAN'],
            'GEOMETRY' => ['type' => Column\Type::TYPE_SPATIAL_GEOMETRY, 'native' => null],
            'NULL' => ['type' => Column\Type::TYPE_NULL, 'native' => 'NULL']
        ]);

        return $mapping;
    }
}
