<?php

namespace SolubleTest\Metadata\Reader;

use Soluble\Metadata\Exception\EmptyQueryException;
use Soluble\Metadata\Exception\UnsupportedDriverException;
use Soluble\Metadata\Reader\PdoMysqlMetadataReader;
use Soluble\Datatype\Column;
use Soluble\DbWrapper\Adapter\PdoMysqlAdapter;
use PHPUnit\Framework\TestCase;

/**
 * PDO_MySQL in PHP 5.3 does not return column names.
 */
class PdoMysqlMetadataReaderTest extends TestCase
{
    /**
     * @var PdoMysqlMetadataReader
     */
    protected $metadata;

    /**
     * @var PdoMysqlAdapter
     */
    protected $adapter;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->adapter = new PdoMysqlAdapter(\SolubleTestFactories::getDbConnection('pdo:mysql'));
        $this->metadata = new PdoMysqlMetadataReader($this->adapter->getConnection()->getResource());
    }

    /**
     * @return \Soluble\Metadata\Reader\PdoMysqlMetadataReader
     */
    public function getReader($conn)
    {
        return new PdoMysqlMetadataReader($conn);
    }

    public function testConstructThrowsUnsupportedDriverException()
    {
        self::expectException(UnsupportedDriverException::class);
        // Fake adapter
        $conn = new \PDO('sqlite::memory:');
        $this->getReader($conn);
    }

    public function testGetColumnsMetadata()
    {
        $sql = 'select * from test_table_types';

        $conn = $this->adapter->getConnection()->getResource();
        $metadata = $this->getReader($conn);

        $md = $metadata->getColumnsMetadata($sql);
        self::assertInstanceOf('Soluble\Metadata\ColumnsMetadata', $md);

        self::assertTrue($md['id']->isPrimary());
        self::assertEquals(Column\Type::TYPE_INTEGER, $md['id']->getDatatype());
        self::assertEquals('test_table_types', $md['id']->getTableName());
        self::assertFalse($md['id']->isNullable());
        self::assertEquals('test_table_types', $md['id']->getTableAlias());
        self::assertEquals(1, $md['id']->getOrdinalPosition());
        self::assertNull($md['id']->getCatalog());
        self::assertFalse($md['id']->isAutoIncrement());

        // IN PDO We cannot tell if numeric unsigned or not
        self::assertNull($md['id']->isNumericUnsigned());
        self::assertNull($md['id']->getNumericUnsigned());

        self::assertEquals(Column\Type::TYPE_STRING, $md['test_varchar_255']->getDatatype());
        self::assertEquals('VARCHAR', $md['test_varchar_255']->getNativeDatatype());
        self::assertEquals($md['test_char_10']->getDatatype(), Column\Type::TYPE_STRING);
        self::assertEquals('CHAR', $md['test_char_10']->getNativeDatatype());
        // This does not work (bug in mysqli)
        //self::assertEquals($md['test_char_10']->getCharacterMaximumLength(), 10);
        // This does not work (cause utf8 store in multibyte)
        // @todo utf8 support in getCharacterMaximumLength
        //  Divide by 3
        // Sould be self::assertEquals(10, $md['test_char_10']->getCharacterMaximumLength());
        // But returned
        self::assertEquals(30, $md['test_char_10']->getCharacterMaximumLength());

        self::assertEquals(Column\Type::TYPE_BLOB, $md['test_text_2000']->getDatatype());
        self::assertEquals('BLOB', $md['test_text_2000']->getNativeDatatype());

        self::assertEquals(Column\Type::TYPE_STRING, $md['test_binary_3']->getDatatype());
        self::assertEquals('CHAR', $md['test_binary_3']->getNativeDatatype());

        self::assertEquals($md['test_varbinary_10']->getDatatype(), Column\Type::TYPE_STRING);
        self::assertEquals($md['test_varbinary_10']->getNativeDatatype(), 'VARCHAR');

        self::assertEquals($md['test_int_unsigned']->getDatatype(), Column\Type::TYPE_INTEGER);
        // Cannot tell in PDO
        //self::assertTrue($md['test_int_unsigned']->isNumericUnsigned());

        self::assertEquals($md['test_bigint']->getDatatype(), Column\Type::TYPE_INTEGER);
        // Cannot tell in PDO
        //self::assertFalse($md['test_bigint']->isNumericUnsigned());
        self::assertEquals($md['test_bigint']->getNativeDatatype(), 'BIGINT');

        self::assertEquals($md['test_decimal_10_3']->getDatatype(), Column\Type::TYPE_DECIMAL);
        self::assertEquals($md['test_decimal_10_3']->getNativeDatatype(), 'DECIMAL');
        self::assertEquals(3, $md['test_decimal_10_3']->getNumericPrecision());
        self::assertEquals(10, $md['test_decimal_10_3']->getNumericScale());
        self::assertFalse($md['test_decimal_10_3']->getNumericUnsigned());
        self::assertFalse($md['test_decimal_10_3']->isNumericUnsigned());

        self::assertEquals($md['test_float']->getDatatype(), Column\Type::TYPE_FLOAT);
        self::assertEquals($md['test_float']->getNativeDatatype(), 'FLOAT');

        self::assertEquals($md['test_tinyint']->getDatatype(), Column\Type::TYPE_INTEGER);
        self::assertEquals($md['test_tinyint']->getNativeDatatype(), 'TINYINT');

        self::assertEquals($md['test_mediumint']->getDatatype(), Column\Type::TYPE_INTEGER);
        self::assertEquals($md['test_mediumint']->getNativeDatatype(), 'MEDIUMINT');

        self::assertEquals($md['test_double']->getDatatype(), Column\Type::TYPE_FLOAT);
        self::assertEquals($md['test_double']->getNativeDatatype(), 'DOUBLE');

        self::assertEquals($md['test_smallint']->getDatatype(), Column\Type::TYPE_INTEGER);
        self::assertEquals($md['test_smallint']->getNativeDatatype(), 'SMALLINT');

        self::assertEquals($md['test_date']->getDatatype(), Column\Type::TYPE_DATE);
        self::assertEquals($md['test_date']->getNativeDatatype(), 'DATE');

        self::assertEquals($md['test_datetime']->getDatatype(), Column\Type::TYPE_DATETIME);
        self::assertEquals($md['test_datetime']->getNativeDatatype(), 'DATETIME');

        self::assertEquals($md['test_timestamp']->getDatatype(), Column\Type::TYPE_DATETIME);
        self::assertEquals($md['test_timestamp']->getNativeDatatype(), 'TIMESTAMP');

        self::assertEquals($md['test_time']->getDatatype(), Column\Type::TYPE_TIME);
        self::assertEquals($md['test_time']->getNativeDatatype(), 'TIME');

        self::assertEquals($md['test_blob']->getDatatype(), Column\Type::TYPE_BLOB);
        self::assertEquals($md['test_blob']->getNativeDatatype(), 'BLOB');

        self::assertEquals($md['test_tinyblob']->getDatatype(), Column\Type::TYPE_BLOB);
        self::assertEquals($md['test_tinyblob']->getNativeDatatype(), 'BLOB');

        self::assertEquals($md['test_mediumblob']->getDatatype(), Column\Type::TYPE_BLOB);
        self::assertEquals($md['test_mediumblob']->getNativeDatatype(), 'BLOB');

        self::assertEquals($md['test_longblob']->getDatatype(), Column\Type::TYPE_BLOB);
        self::assertEquals($md['test_longblob']->getNativeDatatype(), 'BLOB');

        self::assertEquals(255, $md['test_tinyblob']->getCharacterOctetLength());
        self::assertEquals(16777215, $md['test_mediumblob']->getCharacterOctetLength());
        self::assertEquals(4294967295, $md['test_longblob']->getCharacterOctetLength());

        self::assertEquals($md['test_enum']->getDatatype(), Column\Type::TYPE_STRING);
        self::assertEquals($md['test_enum']->getNativeDatatype(), 'CHAR');

        self::assertEquals($md['test_set']->getDatatype(), Column\Type::TYPE_STRING);
        self::assertEquals($md['test_set']->getNativeDatatype(), 'CHAR');

        self::assertEquals($md['test_bit']->getDatatype(), Column\Type::TYPE_BIT);
        self::assertEquals('BIT', $md['test_bit']->getNativeDatatype());

        self::assertEquals($md['test_bool']->getDatatype(), Column\Type::TYPE_INTEGER);
        self::assertEquals('TINYINT', $md['test_bool']->getNativeDatatype());

        self::assertEquals($md['test_geometry']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
        self::assertNull($md['test_geometry']->getNativeDatatype());

        self::assertEquals($md['test_point']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
        self::assertNull($md['test_point']->getNativeDatatype());

        self::assertEquals($md['test_linestring']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
        self::assertNull($md['test_linestring']->getNativeDatatype());

        self::assertEquals($md['test_polygon']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
        self::assertNull($md['test_polygon']->getNativeDatatype());

        self::assertEquals($md['test_multipolygon']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
        self::assertNull($md['test_multipolygon']->getNativeDatatype());

        self::assertEquals($md['test_multipoint']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
        self::assertNull($md['test_multipoint']->getNativeDatatype());

        self::assertEquals($md['test_multilinestring']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
        self::assertNull($md['test_multilinestring']->getNativeDatatype());

        self::assertEquals($md['test_geometrycollection']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
        self::assertNull($md['test_geometrycollection']->getNativeDatatype());
    }

    public function testGetColumnsMetadataThrowsAmbiguousColumnException()
    {
        self::expectException('Soluble\Metadata\Exception\AmbiguousColumnException');
        $sql = 'select id, test_char_10 as id from test_table_types';
        $conn = $this->adapter->getConnection()->getResource();
        $metadata = $this->getReader($conn);
        $metadata->getColumnsMetadata($sql);
    }

    public function testGetColumnsMetadataThrowsEmptyQueryException()
    {
        self::expectException(EmptyQueryException::class);
        $sql = '';
        $conn = $this->adapter->getConnection()->getResource();
        $metadata = $this->getReader($conn);

        $metadata->getColumnsMetadata($sql);
    }

    public function testGetColumsMetadataMultipleTableFunctions()
    {
        $sql = "
                SELECT 'cool' as test_string,
                        1.1 as test_float,
                        (10/2*3)+1 as test_calc,
                        (1+ mc.container_id) as test_calc_2,
                        m.container_id,
                        mc.container_id as mcid,
                        mc.title,
                        filesize,
                        count(*),
                        null as test_null,
                        max(filemtime),
                        min(filemtime),
                        group_concat(filename),
                        avg(filemtime),
                        count(*) as count_media,
                        max(filemtime) as max_time,
                        min(filemtime) as min_time,
                        group_concat(filename) as files,
                        avg(filemtime) as avg_time,
                        sum(filesize) as sum_filesize

                FROM media m
                inner join media_container mc
                on mc.container_id = m.container_id
                group by 1,2,3,4,5,6,7,8
                order by 9 desc
        ";

        $conn = $this->adapter->getConnection()->getResource();
        $metadata = $this->getReader($conn);

        $md = $metadata->getColumnsMetadata($sql);

        self::assertFalse($md['test_string']->isPrimary());
        self::assertEquals(Column\Type::TYPE_STRING, $md['test_string']->getDatatype());
        self::assertNull($md['test_string']->getTableName());
        self::assertFalse($md['test_string']->isNullable());
        self::assertNull($md['test_string']->getTableAlias());
        self::assertEquals(1, $md['test_string']->getOrdinalPosition());
        // PDO-NOT-POSSIBLE: self::assertEquals('def', $md['test_string']->getCatalog());

        self::assertEquals(Column\Type::TYPE_DECIMAL, $md['test_calc']->getDatatype());
        self::assertNull($md['test_calc']->getTableName());

        self::assertEquals(Column\Type::TYPE_INTEGER, $md['test_calc_2']->getDatatype());
        self::assertFalse($md['test_calc_2']->isAutoIncrement());
        self::assertNull($md['test_calc_2']->getTableName());

        self::assertEquals(Column\Type::TYPE_INTEGER, $md['filesize']->getDatatype());
        // PDO-NOT-POSSIBLE: self::assertEquals('media', $md['filesize']->getTableName());
        // INSTEAD USE
        self::assertEquals('m', $md['filesize']->getTableName());

        self::assertEquals('m', $md['filesize']->getTableAlias());

        self::assertNull($md['test_string']->getSchemaName());
        // PDO-NOT-POSSIBLE: self::assertEquals($this->adapter->getCurrentSchema(), $md['filesize']->getSchemaName());
        // INSTEAD USE
        self::assertNull($md['filesize']->getSchemaName());

        self::assertEquals(Column\Type::TYPE_INTEGER, $md['container_id']->getDatatype());
        // PDO-NOT-POSSIBLE: self::assertEquals('media', $md['container_id']->getTableName());
        self::assertEquals('m', $md['container_id']->getTableAlias());

        self::assertEquals(Column\Type::TYPE_INTEGER, $md['mcid']->getDatatype());
        // PDO-NOT-POSSIBLE: self::assertEquals('media_container', $md['mcid']->getTableName());
        self::assertEquals('mc', $md['mcid']->getTableAlias());

        self::assertEquals(Column\Type::TYPE_INTEGER, $md['max(filemtime)']->getDatatype());
        self::assertEquals(Column\Type::TYPE_INTEGER, $md['max_time']->getDatatype());
        self::assertEquals('INTEGER', $md['max_time']->getNativeDatatype());

        // Testing computed
        self::assertTrue($md['min_time']->isComputed());
        self::assertTrue($md['max_time']->isComputed());
        self::assertTrue($md['avg_time']->isComputed());
        self::assertTrue($md['files']->isComputed());
        self::assertTrue($md['test_string']->isComputed());
        self::assertTrue($md['test_float']->isComputed());
        self::assertTrue($md['test_calc']->isComputed());
        self::assertTrue($md['test_calc_2']->isComputed());
        self::assertFalse($md['container_id']->isComputed());

        // TESTING Aliased

        self::assertEquals('mcid', $md['mcid']->getAlias());
        // PDO-NOT-POSSIBLE: self::assertEquals('container_id', $md['mcid']->getName());
        self::assertEquals('min_time', $md['min_time']->getName());
        self::assertEquals('min_time', $md['min_time']->getAlias());

        // TEST if column is part of a group
        // PDO-NOT-POSSIBLE: self::assertTrue($md['count_media']->isGroup());
        // PDO-NOT-POSSIBLE: self::assertTrue($md['min_time']->isGroup());
        // PDO-NOT-POSSIBLE: self::assertTrue($md['max_time']->isGroup());
        // PDO-NOT-POSSIBLE: self::assertTrue($md['min(filemtime)']->isGroup());
        // PDO-NOT-POSSIBLE: self::assertTrue($md['max(filemtime)']->isGroup());
        // WARNING BUGS IN MYSQL (should be true)
        // PDO-NOT-POSSIBLE: self::assertFalse($md['avg(filemtime)']->isGroup());
        // PDO-NOT-POSSIBLE: self::assertFalse($md['avg_time']->isGroup());
        // PDO-NOT-POSSIBLE: self::assertFalse($md['files']->isGroup());
        // PDO-NOT-POSSIBLE: self::assertFalse($md['group_concat(filename)']->isGroup());
        // Various type returned by using functions
        self::assertEquals(Column\Type::TYPE_INTEGER, $md['count_media']->getDatatype());
        self::assertEquals(Column\Type::TYPE_INTEGER, $md['max_time']->getDatatype());
        self::assertEquals(Column\Type::TYPE_INTEGER, $md['min_time']->getDatatype());
        self::assertEquals(Column\Type::TYPE_DECIMAL, $md['avg_time']->getDatatype());
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }
}
