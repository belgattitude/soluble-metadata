<?php

namespace Soluble\Metadata\Reader\Mapping;

use Soluble\Datatype\Column;
use ArrayObject;

class MysqliMapping
{
    /**
     * @return ArrayObject
     */
    public static function getDatatypeMapping()
    {
        /*
        // ALL the following fields are not supported yet
        // Maybe todo in a later release or choose to map them to approximative
        // types

          MYSQLI_TYPE_YEAR -> int
          MYSQLI_TYPE_ENUM -> string
          MYSQLI_TYPE_SET -> string
         */

        $mapping = new ArrayObject([
            MYSQLI_TYPE_STRING => ['type' => Column\Type::TYPE_STRING, 'native' => 'VARCHAR'],
            MYSQLI_TYPE_CHAR => ['type' => Column\Type::TYPE_STRING, 'native' => 'CHAR'],
            MYSQLI_TYPE_VAR_STRING => ['type' => Column\Type::TYPE_STRING, 'native' => 'VARCHAR'],
            MYSQLI_TYPE_ENUM => ['type' => Column\Type::TYPE_STRING, 'native' => 'ENUM'],
            // BLOBS ARE CURRENTLY SENT AS TEXT
            // I DIDN'T FIND THE WAY TO MAKE THE DIFFERENCE !!!
            MYSQLI_TYPE_TINY_BLOB => ['type' => Column\Type::TYPE_BLOB, 'native' => 'TINYBLOB'],
            MYSQLI_TYPE_MEDIUM_BLOB => ['type' => Column\Type::TYPE_BLOB, 'native' => 'MEDIUMBLOB'],
            MYSQLI_TYPE_LONG_BLOB => ['type' => Column\Type::TYPE_BLOB, 'native' => 'LONGBLOB'],
            MYSQLI_TYPE_BLOB => ['type' => Column\Type::TYPE_BLOB, 'native' => 'BLOB'],
            // integer
            MYSQLI_TYPE_TINY => ['type' => Column\Type::TYPE_INTEGER, 'native' => 'TINYINT'],
            MYSQLI_TYPE_YEAR => ['type' => Column\Type::TYPE_INTEGER, 'native' => 'YEAR'],
            MYSQLI_TYPE_SHORT => ['type' => Column\Type::TYPE_INTEGER, 'native' => 'SMALLINT'],
            MYSQLI_TYPE_INT24 => ['type' => Column\Type::TYPE_INTEGER, 'native' => 'MEDIUMINT'],
            MYSQLI_TYPE_LONG => ['type' => Column\Type::TYPE_INTEGER, 'native' => 'INTEGER'],
            MYSQLI_TYPE_LONGLONG => ['type' => Column\Type::TYPE_INTEGER, 'native' => 'BIGINT'],
            // timestamps
            MYSQLI_TYPE_TIMESTAMP => ['type' => Column\Type::TYPE_DATETIME, 'native' => 'TIMESTAMP'],
            MYSQLI_TYPE_DATETIME => ['type' => Column\Type::TYPE_DATETIME, 'native' => 'DATETIME'],
            // dates
            MYSQLI_TYPE_DATE => ['type' => Column\Type::TYPE_DATE, 'native' => 'DATE'],
            MYSQLI_TYPE_NEWDATE => ['type' => Column\Type::TYPE_DATE, 'native' => 'DATE'],
            // time
            MYSQLI_TYPE_TIME => ['type' => Column\Type::TYPE_TIME, 'native' => 'TIME'],
            // decimals
            MYSQLI_TYPE_DECIMAL => ['type' => Column\Type::TYPE_DECIMAL, 'native' => 'DECIMAL'],
            MYSQLI_TYPE_NEWDECIMAL => ['type' => Column\Type::TYPE_DECIMAL, 'native' => 'DECIMAL'],
            MYSQLI_TYPE_FLOAT => ['type' => Column\Type::TYPE_FLOAT, 'native' => 'FLOAT'],
            MYSQLI_TYPE_DOUBLE => ['type' => Column\Type::TYPE_FLOAT, 'native' => 'DOUBLE'],
            MYSQLI_TYPE_BIT => ['type' => Column\Type::TYPE_BIT, 'native' => 'BIT'],
            //MYSQLI_TYPE_BOOLEAN => array('type' => Column\Type::TYPE_BOOLEAN, 'native' => 'BOOLEAN'),
            MYSQLI_TYPE_GEOMETRY => ['type' => Column\Type::TYPE_SPATIAL_GEOMETRY, 'native' => null],

            // This type is really annoying, it's when the select contains an aliased null constant
            // like 'select null as test_alias'
            MYSQLI_TYPE_NULL => ['type' => Column\Type::TYPE_NULL, 'native' => 'NULL']
        ]);

        return $mapping;
    }
}
