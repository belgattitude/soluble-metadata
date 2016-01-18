<?php

namespace Soluble\Metadata\Reader;

use Soluble\Metadata\ColumnsMetadata;
use Soluble\Metadata\Exception;
use Soluble\Datatype\Column;
use Soluble\Datatype\Column\Exception\UnsupportedDatatypeException;
use ArrayObject;

class MysqliMetadataReader extends AbstractMetadataReader
{

    /**
     * @var \Mysqli
     */
    protected $mysqli;

    /**
     *
     * @var boolean
     */
    protected $cache_active = true;

    /**
     *
     * @var Array
     */
    protected static $metadata_cache = [];

    /**
     *
     * @param \Mysqli $mysqli
     */
    public function __construct(\Mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     *
     * {@inheritdoc}
     */
    protected function readColumnsMetadata($sql)
    {
        $metadata = new ColumnsMetadata();
        $fields = $this->readFields($sql);
        $type_map = $this->getDatatypeMapping();


        foreach ($fields as $idx => $field) {
            $name = $field->orgname == '' ? $field->name : $field->orgname;
            $tableName = $field->orgtable;
            $schemaName = $field->db;

            $datatype = $field->type;


            if (!$type_map->offsetExists($datatype)) {
                throw new UnsupportedDatatypeException("Datatype '$datatype' not yet supported by " . __CLASS__);
            }


            $datatype = $type_map->offsetGet($datatype);

            $column = Column\Type::createColumnDefinition($datatype['type'], $name, $tableName, $schemaName);

            $column->setAlias($field->name);
            $column->setTableAlias($field->table);
            $column->setCatalog($field->catalog);
            $column->setOrdinalPosition($idx + 1);
            $column->setDataType($datatype['type']);
            $column->setIsNullable(!($field->flags & MYSQLI_NOT_NULL_FLAG) > 0 && ($field->orgtable != ''));
            $column->setIsPrimary(($field->flags & MYSQLI_PRI_KEY_FLAG) > 0);

            $column->setColumnDefault($field->def);

            if (($field->flags & MYSQLI_SET_FLAG) > 0) {
                $column->setNativeDataType('SET');
            } elseif (($field->flags & MYSQLI_ENUM_FLAG) > 0) {
                $column->setNativeDataType('ENUM');
            } else {
                $column->setNativeDataType($datatype['native']);
            }

            if ($field->table == '') {
                $column->setIsGroup(($field->flags & MYSQLI_GROUP_FLAG) > 0);
            }

            if ($column instanceof Column\Definition\NumericColumnInterface) {
                $column->setNumericUnsigned(($field->flags & MYSQLI_UNSIGNED_FLAG) > 0);
            }

            if ($column instanceof Column\Definition\IntegerColumn) {
                $column->setIsAutoIncrement(($field->flags & MYSQLI_AUTO_INCREMENT_FLAG) > 0);
            }

            if ($column instanceof Column\Definition\DecimalColumn) {
                // salary DECIMAL(5,2)
                // In this example, 5 is the precision and 2 is the scale.
                // Standard SQL requires that DECIMAL(5,2) be able to store any value
                // with five digits and two decimals, so values that can be stored in
                // the salary column range from -999.99 to 999.99.

                $column->setNumericScale($field->length - $field->decimals + 1);
                $column->setNumericPrecision($field->decimals);
            }

            if ($column instanceof Column\Definition\StringColumn) {
                $column->setCharacterMaximumLength($field->length);
            }

            if ($column instanceof Column\Definition\BlobColumn) {
                $column->setCharacterOctetLength($field->length);
            }

            $alias = $column->getAlias();

            if ($metadata->offsetExists($alias)) {
                $prev_column = $metadata->offsetGet($alias);
                $prev_def = $prev_column->toArray();
                $curr_def = $column->toArray();
                if ($prev_def['dataType'] != $curr_def['dataType'] || $prev_def['nativeDataType'] != $curr_def['nativeDataType']) {
                    throw new Exception\AmbiguousColumnException("Cannot get column metadata, non unique column found '$alias' in query with different definitions.");
                }

                // If the the previous definition, was a prev_def
                if ($prev_def['isPrimary']) {
                    $column = $prev_column;
                }
            }


            $metadata->offsetSet($alias, $column);
        }

        return $metadata;
    }

    /**
     *
     * @param string $sql
     * @throws Exception\ConnectionException
     * @return array
     */
    protected function readFields($sql)
    {
        if (trim($sql) == '') {
            throw new Exception\EmptyQueryException(__METHOD__ . ": Error cannot handle empty queries");
        }

        $sql = $this->makeQueryEmpty($sql);

        $stmt = $this->mysqli->prepare($sql);

        if (!$stmt) {
            $message = $this->mysqli->error;
            throw new Exception\InvalidQueryException(__METHOD__ . ": Error sql is not correct : $message");
        }
        $stmt->execute();

        $result = $stmt->result_metadata();
        $metaFields = $result->fetch_fields();
        $result->close();
        $stmt->close();
        return $metaFields;
    }

    /**
     *
     * @return ArrayObject
     */
    protected function getDatatypeMapping()
    {
        // ALL the following fields are not supported yet
        // Maybe todo in a later release or choose to map them to approximative
        // types (i.e. MYSQLI_YEAR could be a integer) ?
        /*
          MYSQLI_TYPE_NULL
          MYSQLI_TYPE_YEAR
          MYSQLI_TYPE_ENUM
          MYSQLI_TYPE_SET
          MYSQLI_TYPE_GEOMETRY
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
        ]);


        // enum

        return $mapping;
    }
}
