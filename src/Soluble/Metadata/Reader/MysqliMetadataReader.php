<?php

declare(strict_types=1);

namespace Soluble\Metadata\Reader;

use Soluble\Metadata\ColumnsMetadata;
use Soluble\Metadata\Exception;
use Soluble\Metadata\Reader\Capability\ReaderCapabilityInterface;
use Soluble\Metadata\Reader\Mapping\MysqliMapping;
use Soluble\Datatype\Column;

class MysqliMetadataReader extends AbstractMetadataReader
{
    /**
     * @var \mysqli
     */
    protected $mysqli;

    /**
     * @var array
     */
    protected static $metadata_cache = [];

    /**
     * @param \mysqli $mysqli
     */
    public function __construct(\mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->setupCapabilities();
    }

    protected function setupCapabilities(): void
    {
        $caps = [
            ReaderCapabilityInterface::DETECT_GROUP_FUNCTION,
            ReaderCapabilityInterface::DETECT_PRIMARY_KEY,
            ReaderCapabilityInterface::DETECT_NUMERIC_UNSIGNED,
            ReaderCapabilityInterface::DETECT_AUTOINCREMENT,
            //ReaderCapabilityInterface::DETECT_COLUMN_DEFAULT,
            //ReaderCapabilityInterface::DETECT_CHAR_MAX_LENGTH,
        ];
        foreach ($caps as $cap) {
            $this->addCapability($cap);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Soluble\Metadata\Exception\ConnectionException
     * @throws \Soluble\Metadata\Exception\EmptyQueryException
     * @throws \Soluble\Metadata\Exception\InvalidQueryException
     */
    protected function readColumnsMetadata(string $sql): ColumnsMetadata
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
            $column->setIsNullable(!(($field->flags & MYSQLI_NOT_NULL_FLAG) > 0) && ($field->orgtable !== '' || $field->orgtable !== null));
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
                $column->setNumericScale((int) ($field->length - $field->decimals + 1));
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
                /*
                if ($prev_def['isPrimary']) {
                    $column = $prev_column;
                }*/
            }

            $metadata->offsetSet($alias, $column);
        }

        return $metadata;
    }

    /**
     * @throws Exception\ConnectionException
     * @throws \Soluble\Metadata\Exception\EmptyQueryException
     * @throws \Soluble\Metadata\Exception\InvalidQueryException
     *
     * @return array<int, mixed>
     */
    protected function readFields(string $sql): array
    {
        if (trim($sql) === '') {
            throw new Exception\EmptyQueryException('Cannot read fields for an empty query');
        }

        $sql = $this->getEmptiedQuery($sql);
        $stmt = $this->mysqli->prepare($sql);

        if ($stmt === false) {
            $message = $this->mysqli->error;
            throw new Exception\InvalidQueryException(
                sprintf('Invalid query: %s (%s)', $sql, $message)
            );
        }
        $stmt->execute();

        $result = $stmt->result_metadata();
        if ($result === false) {
            $message = $this->mysqli->error;
            throw new Exception\InvalidQueryException(
                sprintf('Cannot get metadata: %s (%s)', $sql, $message)
            );
        }

        $metaFields = $result->fetch_fields();

        if ($metaFields === false) {
            $result->close();
            $message = $this->mysqli->error;
            throw new Exception\InvalidQueryException(
                sprintf('Cannot fetch metadata fields: %s (%s)', $sql, $message)
            );
        }

        $result->close();
        $stmt->close();

        return $metaFields;
    }
}
