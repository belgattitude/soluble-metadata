<?php
namespace Soluble\FlexStore\Metadata\Reader;

use Soluble\FlexStore\Metadata\Exception;
use Soluble\Db\Metadata\Column;
use Soluble\Db\Metadata\Column\Types;
use Soluble\Db\Metadata\Column\Exception\UnsupportedDatatypeException;
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
    protected static $metadata_cache = array();

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
     * @param string $sql
     * @return \ArrayObject
     * @throws UnsupportedDatatypeException
     * @throws Exception\AmbiguousColumnException
     * @throws Exception\ConnectionException
     */
    protected function readColumnsMetadata($sql)
    {
        $metadata = new ArrayObject();
        $fields = $this->readFields($sql);
        $type_map = $this->getDatatypeMapping();


        foreach ($fields as $idx => $field) {
            $name = $field->orgname == '' ? $field->name : $field->orgname;
            $tableName = $field->orgtable;
            $schemaName = $field->db;

            $datatype = $field->type;

            //@codeCoverageIgnoreStart
            if (!$type_map->offsetExists($datatype)) {
                throw new UnsupportedDatatypeException("Datatype '$datatype' not yet supported by " . __CLASS__);
            }
            //@codeCoverageIgnoreEnd

            $datatype = $type_map->offsetGet($datatype);

            $column = Column\Type::createColumnDefinition($datatype['type'], $name, $tableName, $schemaName);
            /*
            if ($field->name == 'min_time') {
                var_dump($field);
            //	var_dump($field->flags & MYSQLI_BLOB_FLAG);
            //	var_dump($field->flags & MYSQLI_ENUM_FLAG);
                die();
            }*/
/*
    MYSQLI_BINARY_FLAG
    MYSQLI_BLOB_FLAG
    MYSQLI_ENUM_FLAG
    MYSQLI_MULTIPLE_KEY_FLAG
    MYSQLI_GROUP_FLAG
    MYSQLI_SET_FLAG
    MYSQLI_UNIQUE_KEY_FLAG
    MYSQLI_ZEROFILL_FLAG
*/

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
                if ($prev_def['dataType'] != $curr_def['dataType']
                    ||  $prev_def['nativeDataType'] != $curr_def['nativeDataType']) {
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

        // to check if query is empty
        /*
            $stmt->store_result();
            var_dump($stmt->num_rows);
            var_dump(
         */

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

        $mapping = new ArrayObject(array(
            MYSQLI_TYPE_STRING      => array('type' => Column\Type::TYPE_STRING, 'native' => 'VARCHAR'),
            MYSQLI_TYPE_CHAR        => array('type' => Column\Type::TYPE_STRING, 'native' => 'CHAR'),
            MYSQLI_TYPE_VAR_STRING  => array('type' => Column\Type::TYPE_STRING, 'native' => 'VARCHAR'),

            MYSQLI_TYPE_ENUM => array('type' => Column\Type::TYPE_STRING, 'native' => 'ENUM'),

            // BLOBS ARE CURRENTLY SENT AS TEXT
            // I DIDN'T FIND THE WAY TO MAKE THE DIFFERENCE !!!


            MYSQLI_TYPE_TINY_BLOB => array('type' => Column\Type::TYPE_BLOB, 'native' => 'TINYBLOB'),
            MYSQLI_TYPE_MEDIUM_BLOB => array('type' => Column\Type::TYPE_BLOB, 'native' => 'MEDIUMBLOB'),
            MYSQLI_TYPE_LONG_BLOB => array('type' => Column\Type::TYPE_BLOB, 'native' => 'LONGBLOB'),
            MYSQLI_TYPE_BLOB => array('type' => Column\Type::TYPE_BLOB, 'native' => 'BLOB'),




            // integer
            MYSQLI_TYPE_TINY => array('type' => Column\Type::TYPE_INTEGER, 'native' => 'TINYINT'),
            MYSQLI_TYPE_YEAR => array('type' => Column\Type::TYPE_INTEGER, 'native' => 'YEAR'),
            MYSQLI_TYPE_SHORT => array('type' => Column\Type::TYPE_INTEGER, 'native' => 'SMALLINT'),
            MYSQLI_TYPE_INT24 => array('type' => Column\Type::TYPE_INTEGER, 'native' => 'MEDIUMINT'),
            MYSQLI_TYPE_LONG => array('type' => Column\Type::TYPE_INTEGER, 'native' => 'INTEGER'),
            MYSQLI_TYPE_LONGLONG => array('type' => Column\Type::TYPE_INTEGER, 'native' => 'BIGINT'),

            // timestamps
            MYSQLI_TYPE_TIMESTAMP => array('type' => Column\Type::TYPE_DATETIME, 'native' => 'TIMESTAMP'),
            MYSQLI_TYPE_DATETIME => array('type' => Column\Type::TYPE_DATETIME, 'native' => 'DATETIME'),

            // dates
            MYSQLI_TYPE_DATE => array('type' => Column\Type::TYPE_DATE, 'native' => 'DATE'),
            MYSQLI_TYPE_NEWDATE => array('type' => Column\Type::TYPE_DATE, 'native' => 'DATE'),

            // time
            MYSQLI_TYPE_TIME => array('type' => Column\Type::TYPE_TIME, 'native' => 'TIME'),

            // decimals
            MYSQLI_TYPE_DECIMAL => array('type' => Column\Type::TYPE_DECIMAL, 'native' => 'DECIMAL'),
            MYSQLI_TYPE_NEWDECIMAL => array('type' => Column\Type::TYPE_DECIMAL, 'native' => 'DECIMAL'),

            MYSQLI_TYPE_FLOAT => array('type' => Column\Type::TYPE_FLOAT, 'native' => 'FLOAT'),
            MYSQLI_TYPE_DOUBLE => array('type' => Column\Type::TYPE_FLOAT, 'native' => 'DOUBLE'),





            MYSQLI_TYPE_BIT => array('type' => Column\Type::TYPE_BIT, 'native' => 'BIT'),
            //MYSQLI_TYPE_BOOLEAN => array('type' => Column\Type::TYPE_BOOLEAN, 'native' => 'BOOLEAN'),

            MYSQLI_TYPE_GEOMETRY => array('type' => Column\Type::TYPE_SPATIAL_GEOMETRY, 'native' => null),


        ));


        // enum

        return $mapping;
    }
}
