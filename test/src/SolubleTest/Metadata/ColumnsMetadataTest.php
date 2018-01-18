<?php

namespace SolubleTest\Metadata;

use Soluble\Datatype\Column\Definition\AbstractColumnDefinition;
use Soluble\Datatype\Column\Definition\IntegerColumn;
use Soluble\Metadata\Exception\InvalidQueryException;
use Soluble\Metadata\Exception\TableNotFoundException;
use Soluble\Metadata\Reader;
use Soluble\Datatype\Column;
use PHPUnit\Framework\TestCase;

class ColumnsMetadataTest extends TestCase
{
    /**
     * @var array
     */
    protected $readers;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $pdo_mysql = \SolubleTestFactories::getDbConnection('pdo:mysql');
        $mysqli = \SolubleTestFactories::getDbConnection('mysqli');

        $this->readers = [
            'mysqli' => new Reader\MysqliMetadataReader($mysqli),
            'pdo_mysql' => new Reader\PdoMysqlMetadataReader($pdo_mysql)
        ];
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    public function testIterableColumns()
    {
        $sql = 'select * from test_table_types';
        foreach ($this->readers as $reader_type => $reader) {
            $md = $reader->getColumnsMetadata($sql);
            foreach ($md as $column) {
                self::assertInstanceOf(AbstractColumnDefinition::class, $column);
            }
        }
    }

    public function testGetColumn()
    {
        $sql = 'select * from test_table_types';
        foreach ($this->readers as $reader_type => $reader) {
            $md = $reader->getColumnsMetadata($sql);
            $id_column = $md->getColumn('id');
            self::assertInstanceOf(AbstractColumnDefinition::class, $id_column);
            self::assertInstanceOf(IntegerColumn::class, $id_column);
            self::assertEquals($md['id'], $id_column);
            try {
                $md->getColumn('NOTACOLUMN');
                self::assertFalse(true, "Get column on '$reader_type' should throw an exception.");
            } catch (\Soluble\Metadata\Exception\UnexistentColumnException $ex) {
                // do nothing
            }
        }
    }

    public function testGetColumnThrowsInvalidQueryException()
    {
        $sql = 'select*FROM';
        foreach ($this->readers as $reader_type => $reader) {
            try {
                $md = $reader->getColumnsMetadata($sql);
                self::assertFalse(false, 'An exception should be thrown. InvalidQueryException');
            } catch (InvalidQueryException $e) {
                self::assertTrue(true);
            } catch (\Exception $e) {
                self::assertFalse(false, 'InvalidQueryException should be thrown');
            }
        }
    }

    public function testGetTableMetadata()
    {
        $table = 'test_table_types';
        foreach ($this->readers as $reader_type => $reader) {
            $md = $reader->getTableMetadata($table);
            $id_column = $md->getColumn('id');
            self::assertInstanceOf(AbstractColumnDefinition::class, $id_column);
            self::assertInstanceOf(IntegerColumn::class, $id_column);
            self::assertEquals($md['id'], $id_column);
            try {
                $md->getColumn('NOTACOLUMN');
                self::assertFalse(true, "Get column on '$reader_type' should throw an exception.");
            } catch (\Soluble\Metadata\Exception\UnexistentColumnException $ex) {
                // do nothing
            }
        }
    }

    public function testGetTableMetadataThrowsTableNotFoundException()
    {
        $table = 'table_not_existssss';
        foreach ($this->readers as $reader_type => $reader) {
            try {
                $md = $reader->getTableMetadata($table);
                self::assertFalse(false, 'An exception should be thrown. TableNotFoundException');
            } catch (TableNotFoundException $e) {
                self::assertTrue(true);
            } catch (\Exception $e) {
                self::assertFalse(false, 'TableNotFoundException must be thrown');
            }
        }
    }
}
