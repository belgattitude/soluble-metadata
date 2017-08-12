<?php

namespace Soluble\Metadata\Reader;

use Soluble\Metadata\ColumnsMetadata;
use Soluble\Metadata\Exception;
use Soluble\Metadata\Reader\Mapping\MysqliMapping;
use Soluble\Datatype\Column;

class MysqliMetadataReader extends AbstractMetadataReader
{
    /**
     * @var \Mysqli
     */
    protected $mysqli;

    /**
     * @var array
     */
    protected static $metadata_cache = [];

    /**
     * @param \Mysqli $mysqli
     */
    public function __construct(\Mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Soluble\Metadata\Exception\ConnectionException
     * @throws \Soluble\Metadata\Exception\EmptyQueryException
     * @throws \Soluble\Metadata\Exception\InvalidQueryException
     */
    protected function readColumnsMetadata($sql)
    {
        $metadata = new ColumnsMetadata();
        $fields = $this->readFields($sql);
        $type_map = MysqliMapping::getDatatypeMapping();

        foreach ($fields as $idx => $field) {
            $name = $field->orgname === '' ? $field->name : $field->orgname;
            $tableName = $field->orgtable;
            $schemaName = $field->db;

            $type = $field->type;

            if (!$type_map->offsetExists($type)) {
                $msg = "Cannot get type for field '$name'. Mapping for native type [$type] cannot be resolved into a valid type for driver: " . __CLASS__;
                throw new Exception\UnsupportedTypeException($msg);
            }

            $datatype = $type_map->offsetGet($type);

            $column = Column\Type::createColumnDefinition($datatype['type'], $name, $tableName, $schemaName);

            $column->setAlias($field->name);
            $column->setTableAlias($field->table);
            $column->setCatalog($field->catalog);
            $column->setOrdinalPosition($idx + 1);
            $column->setDataType($datatype['type']);
            $column->setIsNullable(!($field->flags & MYSQLI_NOT_NULL_FLAG) > 0 && ($field->orgtable !== '' || $field->orgtable !== null));
            $column->setIsPrimary(($field->flags & MYSQLI_PRI_KEY_FLAG) > 0);

            $column->setColumnDefault($field->def);

            if (($field->flags & MYSQLI_SET_FLAG) > 0) {
                $column->setNativeDataType('SET');
            } elseif (($field->flags & MYSQLI_ENUM_FLAG) > 0) {
                $column->setNativeDataType('ENUM');
            } else {
                $column->setNativeDataType($datatype['native']);
            }

            if ($field->table === '' || $field->table === null) {
                $column->setIsGroup(($field->flags & MYSQLI_GROUP_FLAG) > 0);
            }

            if ($column instanceof Column\Definition\NumericColumnInterface) {
                $column->setNumericUnsigned(($field->flags & MYSQLI_UNSIGNED_FLAG) > 0);
            }

            if ($column instanceof Column\Definition\IntegerColumn) {
                $column->setIsAutoIncrement(($field->flags & MYSQLI_AUTO_INCREMENT_FLAG) > 0);
            } elseif ($column instanceof Column\Definition\DecimalColumn) {
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
                if ($prev_def['dataType'] !== $curr_def['dataType'] || $prev_def['nativeDataType'] !== $curr_def['nativeDataType']) {
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
     * @param string $sql
     *
     * @throws Exception\ConnectionException
     *
     * @return array
     *
     * @throws \Soluble\Metadata\Exception\EmptyQueryException
     * @throws \Soluble\Metadata\Exception\InvalidQueryException
     */
    protected function readFields($sql)
    {
        if (trim($sql) === '') {
            throw new Exception\EmptyQueryException(__METHOD__ . ': Error cannot handle empty queries');
        }

        $sql = $this->getEmptiedQuery($sql);
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
}
