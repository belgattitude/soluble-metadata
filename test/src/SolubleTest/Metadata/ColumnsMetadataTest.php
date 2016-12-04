<?php

namespace SolubleTest\Metadata;

use Soluble\Metadata\Reader;
use Soluble\Datatype\Column;

class ColumnsMetadataTest extends \PHPUnit_Framework_TestCase
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
                $this->assertInstanceOf('Soluble\Datatype\Column\Definition\AbstractColumnDefinition', $column);
            }
        }
    }

    public function testGetColumn()
    {
        $sql = 'select * from test_table_types';
        foreach ($this->readers as $reader_type => $reader) {
            $md = $reader->getColumnsMetadata($sql);
            $id_column = $md->getColumn('id');
            $this->assertInstanceOf('Soluble\Datatype\Column\Definition\AbstractColumnDefinition', $id_column);
            $this->assertInstanceOf('Soluble\Datatype\Column\Definition\IntegerColumn', $id_column);
            $this->assertEquals($md['id'], $id_column);
            try {
                $md->getColumn('NOTACOLUMN');
                $this->assertFalse(true, "Get column on '$reader_type' should throw an exception.");
            } catch (\Soluble\Metadata\Exception\UnexistentColumnException $ex) {
                // do nothing
            }
        }
    }
}
