<?php

namespace Soluble\Metadata\Reader;

use Soluble\Metadata\ColumnsMetadata;
use Soluble\Metadata\Exception;
use Soluble\Datatype\Column;
use Soluble\Db\Metadata\Column\Exception\UnsupportedDatatypeException;
use ArrayObject;
use PDO;

class PdoMysqlMetadataReader extends AbstractMetadataReader
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
    protected static $metadata_cache = [];

    /**
     *
     * @param PDO $pdo
     * @throws Exception\UnsupportedFeatureException
     * @throws Exception\UnsupportedDriverException
     */
    public function __construct(PDO $pdo)
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (strtolower($driver) != 'mysql') {
            throw new Exception\UnsupportedDriverException(__CLASS__ . " supports only pdo_mysql driver, '$driver' given.");
        }
        $this->pdo = $pdo;
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
     * Read fields from pdo source
     * 
     * @throws Exception\ConnectionException
     * @param string $sql
     * @return array
     */
    protected function readFields($sql)
    {
        if (trim($sql) == '') {
            throw new Exception\EmptyQueryException();
        }

        $sql = $this->makeQueryEmpty($sql);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $column_count = $stmt->columnCount();
        $metaFields = [];
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
        $mapping = new ArrayObject([
            'STRING' => ['type' => Column\Type::TYPE_STRING, 'native' => 'CHAR'],
            'VAR_STRING' => ['type' => Column\Type::TYPE_STRING, 'native' => 'VARCHAR'],
            // BLOBS ARE CURRENTLY SENT AS TEXT
            // I DIDN'T FIND THE WAY TO MAKE THE DIFFERENCE !!!
            'BLOB' => ['type' => Column\Type::TYPE_BLOB, 'native' => 'BLOB'],
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
            'GEOMETRY' => ['type' => Column\Type::TYPE_SPATIAL_GEOMETRY, 'native' => null]
        ]);


        // enum

        return $mapping;
    }
}
