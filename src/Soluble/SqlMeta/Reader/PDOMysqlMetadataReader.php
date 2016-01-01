<?php
namespace Soluble\FlexStore\Metadata\Reader;

use Soluble\FlexStore\Metadata\Exception;
use Soluble\Db\Metadata\Column;
use Soluble\Db\Metadata\Column\Types;
use Soluble\Db\Metadata\Column\Exception\UnsupportedDatatypeException;
use ArrayObject;
use PDO;

class PDOMysqlMetadataReader extends AbstractMetadataReader
{
    /**
     * @var PDO
     */
    protected $pdo;


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
     * @param PDO $pdo
     * @throws Exception\UnsupportedFeatureException
     * @throws Exception\UnsupportedDriverException
     */
    public function __construct(PDO $pdo)
    {
        //@codeCoverageIgnoreStart
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            $msg = "PDOMysqlMetadataSource only supported on PHP 5.4+, try to use MysqliMetadatSource instead.";
            throw new Exception\UnsupportedFeatureException($msg);
        };
        //@codeCoverageIgnoreEnd

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (strtolower($driver) != 'mysql') {
            throw new Exception\UnsupportedDriverException(__CLASS__ . " supports only pdo_mysql driver, '$driver' given.");
        }

        $this->pdo = $pdo;
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
            $name = $field['name'];
            $tableName = $field['table'];

            $datatype = strtoupper($field['native_type']);


            //@codeCoverageIgnoreStart
            if (!$type_map->offsetExists($datatype)) {
                throw new UnsupportedDatatypeException("Datatype '$datatype' not yet supported by " . __CLASS__);
            }
            //@codeCoverageIgnoreEnd

            $datatype = $type_map->offsetGet($datatype);

            $column = Column\Type::createColumnDefinition($datatype['type'], $name, $tableName, $schemaName = null);
            $alias = $field['name'];


            $column->setAlias($alias);
            $column->setTableAlias($field['table']);
            //$column->setCatalog($field->catalog);
            $column->setOrdinalPosition($idx + 1);
            $column->setDataType($datatype['type']);
            $column->setIsNullable(!in_array('not_null', $field['flags']));
            $column->setIsPrimary(in_array('primary_key', $field['flags']));
            //$column->setColumnDefault($field->def);
            $column->setNativeDataType($datatype['native']);

/*
            if ($column instanceof Column\Definition\NumericColumnInterface) {
                $column->setNumericUnsigned(($field->flags & MYSQLI_UNSIGNED_FLAG) > 0);
            }

            if ($column instanceof Column\Definition\IntegerColumn) {
                $column->setIsAutoIncrement(($field->flags & MYSQLI_AUTO_INCREMENT_FLAG) > 0);
            }
*/
            if ($column instanceof Column\Definition\DecimalColumn) {
                // salary DECIMAL(5,2)
                // In this example, 5 is the precision and 2 is the scale.
                // Standard SQL requires that DECIMAL(5,2) be able to store any value
                // with five digits and two decimals, so values that can be stored in
                // the salary column range from -999.99 to 999.99.

                $column->setNumericUnsigned(false);
                $column->setNumericPrecision($field['precision']);
                $column->setNumericScale($field['len'] - $field['precision'] + 1);
            }

            if ($column instanceof Column\Definition\StringColumn) {
                $column->setCharacterMaximumLength($field['len']);
            }

            if ($column instanceof Column\Definition\BlobColumn) {
                $column->setCharacterOctetLength($field['len']);
            }

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
            throw new Exception\EmptyQueryException();
        }

        $sql = $this->makeQueryEmpty($sql);
        /*
        if ($this->mysqli->connect_error) {
            $errno = $this->mysqli->connect_errno;
            $message = $this->mysqli->connect_error;
            throw new Exception\ConnectionException("Connection error: $message ($errno)");
        }
         *
         */

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $column_count = $stmt->columnCount();
        $metaFields = array();
        for ($i = 0; $i < $column_count; $i++) {
            $meta = $stmt->getColumnMeta($i);
            $metaFields[$i] = $meta;
        }

        $stmt->closeCursor();
        unset($stmt);
        return $metaFields;
    }




    /**
     *
     * @return ArrayObject
     */
    protected function getDatatypeMapping()
    {
        $mapping = new ArrayObject(array(
            'STRING'        => array('type' => Column\Type::TYPE_STRING, 'native' => 'CHAR'),
            'VAR_STRING'    => array('type' => Column\Type::TYPE_STRING, 'native' => 'VARCHAR'),



            // BLOBS ARE CURRENTLY SENT AS TEXT
            // I DIDN'T FIND THE WAY TO MAKE THE DIFFERENCE !!!

            'BLOB' => array('type' => Column\Type::TYPE_BLOB, 'native' => 'BLOB'),

            // integer
            'TINY' => array('type' => Column\Type::TYPE_INTEGER, 'native' => 'TINYINT'),

            'SHORT' => array('type' => Column\Type::TYPE_INTEGER, 'native' => 'SMALLINT'),
            'INT24' => array('type' => Column\Type::TYPE_INTEGER, 'native' => 'MEDIUMINT'),
            'LONG' => array('type' => Column\Type::TYPE_INTEGER, 'native' => 'INTEGER'),
            'LONGLONG' => array('type' => Column\Type::TYPE_INTEGER, 'native' => 'BIGINT'),

            // timestamps
            'TIMESTAMP' => array('type' => Column\Type::TYPE_DATETIME, 'native' => 'TIMESTAMP'),
            'DATETIME' => array('type' => Column\Type::TYPE_DATETIME, 'native' => 'DATETIME'),

            // dates
            'DATE' => array('type' => Column\Type::TYPE_DATE, 'native' => 'DATE'),
            'NEWDATE' => array('type' => Column\Type::TYPE_DATE, 'native' => 'DATE'),

            // time
            'TIME' => array('type' => Column\Type::TYPE_TIME, 'native' => 'TIME'),

            // decimals
            'DECIMAL' => array('type' => Column\Type::TYPE_DECIMAL, 'native' => 'DECIMAL'),
            'NEWDECIMAL' => array('type' => Column\Type::TYPE_DECIMAL, 'native' => 'DECIMAL'),

            'FLOAT' => array('type' => Column\Type::TYPE_FLOAT, 'native' => 'FLOAT'),
            'DOUBLE' => array('type' => Column\Type::TYPE_FLOAT, 'native' => 'DOUBLE'),



            // boolean

            'BIT' => array('type' => Column\Type::TYPE_BIT, 'native' => 'BIT'),
            'BOOLEAN' => array('type' => Column\Type::TYPE_BOOLEAN, 'native' => 'BOOLEAN'),
            'GEOMETRY' => array('type' => Column\Type::TYPE_SPATIAL_GEOMETRY, 'native' => null)

        ));


        // enum

        return $mapping;
    }
}
