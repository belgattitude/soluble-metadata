<?php

declare(strict_types=1);

namespace Soluble\Metadata\Reader;

use Soluble\Metadata\ColumnsMetadata;
use Soluble\Metadata\Exception;
use Soluble\Datatype\Column;
use Soluble\Metadata\Reader\Capability\ReaderCapabilityInterface;
use Soluble\Metadata\Reader\Mapping\PdoMysqlMapping;
use PDO;

class PdoMysqlMetadataReader extends AbstractMetadataReader
{
    /**
     * @var PDO
     */
    protected $pdo;

    /**
     * @var array
     */
    protected static $metadata_cache = [];

    /**
     * @param PDO $pdo
     *
     * @throws Exception\UnsupportedFeatureException
     * @throws Exception\UnsupportedDriverException
     */
    public function __construct(PDO $pdo)
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (strtolower($driver) !== 'mysql') {
            throw new Exception\UnsupportedDriverException(__CLASS__ . " supports only pdo_mysql driver, '$driver' given.");
        }
        $this->pdo = $pdo;
        $this->setupCapabilities();
    }

    protected function setupCapabilities(): void
    {
        $caps = [
            ReaderCapabilityInterface::DETECT_PRIMARY_KEY,
            //ReaderCapabilityInterface::DETECT_GROUP_FUNCTION,
            //ReaderCapabilityInterface::DETECT_NUMERIC_UNSIGNED,
            //ReaderCapabilityInterface::DETECT_AUTOINCREMENT,
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

        $type_map = PdoMysqlMapping::getDatatypeMapping();

        foreach ($fields as $idx => $field) {
            $name = $field['name'];
            $tableName = $field['table'];

            $type = strtoupper($field['native_type']);

            if (!$type_map->offsetExists($type)) {
                $msg = "Cannot get type for field '$name'. Mapping for native type [$type] cannot be resolved into a valid type for driver: " . __CLASS__;
                throw new Exception\UnsupportedTypeException($msg);
            }

            $datatype = $type_map->offsetGet($type);

            $column = Column\Type::createColumnDefinition($datatype['type'], $name, $tableName, $schemaName = null);
            $alias = $field['name'];

            $column->setAlias($alias);
            $column->setTableAlias($field['table']);
            //$column->setCatalog($field->catalog);
            $column->setOrdinalPosition($idx + 1);
            $column->setDataType($datatype['type']);
            $column->setIsNullable(!in_array('not_null', $field['flags'], true));
            $column->setIsPrimary(in_array('primary_key', $field['flags'], true));
            //$column->setColumnDefault($field->def);
            $column->setNativeDataType($datatype['native']);

            /*
              if ($column instanceof Column\Definition\NumericColumnInterface) {
              $column->setNumericUnsigned(($field->flags & MYSQLI_UNSIGNED_FLAG) > 0);
              }
*/
            if ($column instanceof Column\Definition\IntegerColumn) {
                // PDO does not support detection of autoincrement.
                // Always false

                $column->setIsAutoIncrement(false);
            }

            if ($column instanceof Column\Definition\DecimalColumn) {
                // salary DECIMAL(5,2)
                // In this example, 5 is the precision and 2 is the scale.
                // Standard SQL requires that DECIMAL(5,2) be able to store any value
                // with five digits and two decimals, so values that can be stored in
                // the salary column range from -999.99 to 999.99.

                $column->setNumericUnsigned(false);
                $column->setNumericPrecision($field['precision']);
                $column->setNumericScale((int) ($field['len'] - $field['precision'] + 1));
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
                if ($prev_def['dataType'] !== $curr_def['dataType'] || $prev_def['nativeDataType'] !== $curr_def['nativeDataType']) {
                    throw new Exception\AmbiguousColumnException("Cannot get column metadata, non unique column found '$alias' in query with different definitions.");
                }

                // If the the previous definition, was a prev_def
                /*
                if ($prev_def['isPrimary']) {
                    $column = $prev_column;
                }
                */
            }
            $metadata->offsetSet($alias, $column);
        }

        return $metadata;
    }

    /**
     * Read fields from pdo source.
     *
     * @return array<int, array>
     *
     * @throws Exception\ConnectionException
     * @throws \Soluble\Metadata\Exception\EmptyQueryException
     * @throws \Soluble\Metadata\Exception\InvalidQueryException
     */
    protected function readFields(string $sql): array
    {
        if (trim($sql) === '') {
            throw new Exception\EmptyQueryException('Cannot read fields for an empty query');
        }

        $sql = $this->getEmptiedQuery($sql);

        $stmt = $this->pdo->prepare($sql);
        if ($stmt->execute() !== true) {
            throw new Exception\InvalidQueryException(
                sprintf('Invalid query: %s', $sql)
            );
        }

        $column_count = $stmt->columnCount();
        $metaFields = [];
        for ($i = 0; $i < $column_count; ++$i) {
            $meta = $stmt->getColumnMeta($i);
            $metaFields[$i] = $meta;
        }

        $stmt->closeCursor();
        unset($stmt);

        return $metaFields;
    }
}
