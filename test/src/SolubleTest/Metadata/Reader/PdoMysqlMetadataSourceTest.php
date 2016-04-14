<?php

namespace SolubleTest\Metadata\Reader;

use Soluble\Metadata\Reader\PdoMysqlMetadataReader;
use Soluble\Datatype\Column;
use Soluble\DbWrapper\Adapter\PdoMysqlAdapter;

/**
 * PDO_MySQL in PHP 5.3 does not return column names
 */
class PdoMysqlMetadataSourceTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var PdoMysqlMetadataReader
     */
    protected $metadata;

    /**
     *
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
     *
     * @return \Soluble\Metadata\Reader\PdoMysqlMetadataReader
     */
    public function getReader($conn)
    {
        return new PdoMysqlMetadataReader($conn);
    }

    public function testConstructThrowsUnsupportedFeatureException()
    {
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            $this->setExpectedException('Soluble\Metadata\Exception\UnsupportedFeatureException');
            $conn = $this->adapter->getConnection()->getResource();
            $metadata = $this->getReader($conn);
        } else {
            $this->assertTrue(true);
        }
    }

    public function testConstructThrowsUnsupportedDriverException()
    {
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            $this->setExpectedException('Soluble\Metadata\Exception\UnsupportedFeatureException');
            $conn = $this->adapter->getConnection()->getResource();
            $metadata = $this->getReader($conn);
        } else {
            $this->setExpectedException('Soluble\Metadata\Exception\UnsupportedDriverException');
            // Fake adapter
            $conn = new \PDO('sqlite::memory:');
            $metadata = $this->getReader($conn);
        }
    }

    public function testGetColumnsMetadata()
    {
        if (version_compare(PHP_VERSION, '5.4.0', '>')) {
            $sql = "select * from test_table_types";

            $conn = $this->adapter->getConnection()->getResource();
            $metadata = $this->getReader($conn);

            $md = $metadata->getColumnsMetadata($sql);
            $this->assertInstanceOf('Soluble\Metadata\ColumnsMetadata', $md);

            $this->assertTrue($md['id']->isPrimary());
            $this->assertEquals(Column\Type::TYPE_INTEGER, $md['id']->getDatatype());
            $this->assertEquals('test_table_types', $md['id']->getTableName());
            $this->assertEquals(false, $md['id']->isNullable());
            $this->assertEquals('test_table_types', $md['id']->getTableAlias());
            $this->assertEquals(1, $md['id']->getOrdinalPosition());
            $this->assertEquals(null, $md['id']->getCatalog());
            $this->assertEquals(null, $md['id']->isAutoIncrement());

            // IN PDO We cannot tell if numeric unsigned or not
            $this->assertEquals(null, $md['id']->isNumericUnsigned());
            $this->assertEquals(null, $md['id']->getNumericUnsigned());


            $this->assertEquals(Column\Type::TYPE_STRING, $md['test_varchar_255']->getDatatype());
            $this->assertEquals('VARCHAR', $md['test_varchar_255']->getNativeDatatype());
            $this->assertEquals($md['test_char_10']->getDatatype(), Column\Type::TYPE_STRING);
            $this->assertEquals('CHAR', $md['test_char_10']->getNativeDatatype());
            // This does not work (bug in mysqli)
            //$this->assertEquals($md['test_char_10']->getCharacterMaximumLength(), 10);
            // This does not work (cause utf8 store in multibyte)
            // @todo utf8 support in getCharacterMaximumLength
            //  Divide by 3
            // Sould be $this->assertEquals(10, $md['test_char_10']->getCharacterMaximumLength());
            // But returned
            $this->assertEquals(30, $md['test_char_10']->getCharacterMaximumLength());

            $this->assertEquals(Column\Type::TYPE_BLOB, $md['test_text_2000']->getDatatype());
            $this->assertEquals('BLOB', $md['test_text_2000']->getNativeDatatype());

            $this->assertEquals(Column\Type::TYPE_STRING, $md['test_binary_3']->getDatatype());
            $this->assertEquals('CHAR', $md['test_binary_3']->getNativeDatatype());

            $this->assertEquals($md['test_varbinary_10']->getDatatype(), Column\Type::TYPE_STRING);
            $this->assertEquals($md['test_varbinary_10']->getNativeDatatype(), 'VARCHAR');


            $this->assertEquals($md['test_int_unsigned']->getDatatype(), Column\Type::TYPE_INTEGER);
            // Cannot tell in PDO
            //$this->assertTrue($md['test_int_unsigned']->isNumericUnsigned());

            $this->assertEquals($md['test_bigint']->getDatatype(), Column\Type::TYPE_INTEGER);
            // Cannot tell in PDO
            //$this->assertFalse($md['test_bigint']->isNumericUnsigned());
            $this->assertEquals($md['test_bigint']->getNativeDatatype(), 'BIGINT');

            $this->assertEquals($md['test_decimal_10_3']->getDatatype(), Column\Type::TYPE_DECIMAL);
            $this->assertEquals($md['test_decimal_10_3']->getNativeDatatype(), 'DECIMAL');
            $this->assertEquals(3, $md['test_decimal_10_3']->getNumericPrecision());
            $this->assertEquals(10, $md['test_decimal_10_3']->getNumericScale());
            $this->assertFalse($md['test_decimal_10_3']->getNumericUnsigned());
            $this->assertFalse($md['test_decimal_10_3']->isNumericUnsigned());


            $this->assertEquals($md['test_float']->getDatatype(), Column\Type::TYPE_FLOAT);
            $this->assertEquals($md['test_float']->getNativeDatatype(), 'FLOAT');


            $this->assertEquals($md['test_tinyint']->getDatatype(), Column\Type::TYPE_INTEGER);
            $this->assertEquals($md['test_tinyint']->getNativeDatatype(), 'TINYINT');

            $this->assertEquals($md['test_mediumint']->getDatatype(), Column\Type::TYPE_INTEGER);
            $this->assertEquals($md['test_mediumint']->getNativeDatatype(), 'MEDIUMINT');


            $this->assertEquals($md['test_double']->getDatatype(), Column\Type::TYPE_FLOAT);
            $this->assertEquals($md['test_double']->getNativeDatatype(), 'DOUBLE');


            $this->assertEquals($md['test_smallint']->getDatatype(), Column\Type::TYPE_INTEGER);
            $this->assertEquals($md['test_smallint']->getNativeDatatype(), 'SMALLINT');

            $this->assertEquals($md['test_date']->getDatatype(), Column\Type::TYPE_DATE);
            $this->assertEquals($md['test_date']->getNativeDatatype(), 'DATE');


            $this->assertEquals($md['test_datetime']->getDatatype(), Column\Type::TYPE_DATETIME);
            $this->assertEquals($md['test_datetime']->getNativeDatatype(), 'DATETIME');

            $this->assertEquals($md['test_timestamp']->getDatatype(), Column\Type::TYPE_DATETIME);
            $this->assertEquals($md['test_timestamp']->getNativeDatatype(), 'TIMESTAMP');


            $this->assertEquals($md['test_time']->getDatatype(), Column\Type::TYPE_TIME);
            $this->assertEquals($md['test_time']->getNativeDatatype(), 'TIME');

            $this->assertEquals($md['test_blob']->getDatatype(), Column\Type::TYPE_BLOB);
            $this->assertEquals($md['test_blob']->getNativeDatatype(), 'BLOB');

            $this->assertEquals($md['test_tinyblob']->getDatatype(), Column\Type::TYPE_BLOB);
            $this->assertEquals($md['test_tinyblob']->getNativeDatatype(), 'BLOB');



            $this->assertEquals($md['test_mediumblob']->getDatatype(), Column\Type::TYPE_BLOB);
            $this->assertEquals($md['test_mediumblob']->getNativeDatatype(), 'BLOB');

            $this->assertEquals($md['test_longblob']->getDatatype(), Column\Type::TYPE_BLOB);
            $this->assertEquals($md['test_longblob']->getNativeDatatype(), 'BLOB');

            $this->assertEquals(255, $md['test_tinyblob']->getCharacterOctetLength());
            $this->assertEquals(16777215, $md['test_mediumblob']->getCharacterOctetLength());
            $this->assertEquals(4294967295, $md['test_longblob']->getCharacterOctetLength());



            $this->assertEquals($md['test_enum']->getDatatype(), Column\Type::TYPE_STRING);
            $this->assertEquals($md['test_enum']->getNativeDatatype(), 'CHAR');


            $this->assertEquals($md['test_set']->getDatatype(), Column\Type::TYPE_STRING);
            $this->assertEquals($md['test_set']->getNativeDatatype(), 'CHAR');

            $this->assertEquals($md['test_bit']->getDatatype(), Column\Type::TYPE_BIT);
            $this->assertEquals('BIT', $md['test_bit']->getNativeDatatype());

            $this->assertEquals($md['test_bool']->getDatatype(), Column\Type::TYPE_INTEGER);
            $this->assertEquals('TINYINT', $md['test_bool']->getNativeDatatype());

            $this->assertEquals($md['test_geometry']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
            $this->assertEquals(null, $md['test_geometry']->getNativeDatatype());

            $this->assertEquals($md['test_point']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
            $this->assertEquals(null, $md['test_point']->getNativeDatatype());

            $this->assertEquals($md['test_linestring']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
            $this->assertEquals(null, $md['test_linestring']->getNativeDatatype());

            $this->assertEquals($md['test_polygon']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
            $this->assertEquals(null, $md['test_polygon']->getNativeDatatype());

            $this->assertEquals($md['test_multipolygon']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
            $this->assertEquals(null, $md['test_multipolygon']->getNativeDatatype());

            $this->assertEquals($md['test_multipoint']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
            $this->assertEquals(null, $md['test_multipoint']->getNativeDatatype());

            $this->assertEquals($md['test_multilinestring']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
            $this->assertEquals(null, $md['test_multilinestring']->getNativeDatatype());

            $this->assertEquals($md['test_geometrycollection']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
            $this->assertEquals(null, $md['test_geometrycollection']->getNativeDatatype());
        } else {
            $this->markTestSkipped('Only valid for PHP 5.4+ version');
        }
    }

    public function testGetColumnsMetadataThrowsAmbiguousColumnException()
    {
        if (version_compare(PHP_VERSION, '5.4.0', '>')) {
            $this->setExpectedException('Soluble\Metadata\Exception\AmbiguousColumnException');
            $sql = "select id, test_char_10 as id from test_table_types";
            $conn = $this->adapter->getConnection()->getResource();
            $metadata = $this->getReader($conn);
            $md = $metadata->getColumnsMetadata($sql);
        } else {
            $this->markTestSkipped('Only valid for PHP 5.4+ version');
        }
    }

    public function testGetColumnsMetadataThrowsEmptyQueryException()
    {
        if (version_compare(PHP_VERSION, '5.4.0', '>')) {
            $this->setExpectedException('Soluble\Metadata\Exception\EmptyQueryException');
            $sql = "";
            $conn = $this->adapter->getConnection()->getResource();
            $metadata = $this->getReader($conn);

            $md = $metadata->getColumnsMetadata($sql);
        } else {
            $this->markTestSkipped('Only valid for PHP 5.4+ version');
        }
    }

    public function testGetColumsMetadataMultipleTableFunctions()
    {
        if (version_compare(PHP_VERSION, '5.4.0', '>')) {
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

            $this->assertEquals(false, $md['test_string']->isPrimary());
            $this->assertEquals(Column\Type::TYPE_STRING, $md['test_string']->getDatatype());
            $this->assertEquals(null, $md['test_string']->getTableName());
            $this->assertEquals(false, $md['test_string']->isNullable());
            $this->assertEquals(null, $md['test_string']->getTableAlias());
            $this->assertEquals(1, $md['test_string']->getOrdinalPosition());
            // PDO-NOT-POSSIBLE: $this->assertEquals('def', $md['test_string']->getCatalog());


            $this->assertEquals(Column\Type::TYPE_DECIMAL, $md['test_calc']->getDatatype());
            $this->assertEquals(null, $md['test_calc']->getTableName());

            $this->assertEquals(Column\Type::TYPE_INTEGER, $md['test_calc_2']->getDatatype());
            $this->assertEquals(false, $md['test_calc_2']->isAutoIncrement());
            $this->assertEquals(null, $md['test_calc_2']->getTableName());

            $this->assertEquals(Column\Type::TYPE_INTEGER, $md['filesize']->getDatatype());
            // PDO-NOT-POSSIBLE: $this->assertEquals('media', $md['filesize']->getTableName());
            // INSTEAD USE
            $this->assertEquals('m', $md['filesize']->getTableName());

            $this->assertEquals('m', $md['filesize']->getTableAlias());

            $this->assertEquals(null, $md['test_string']->getSchemaName());
            // PDO-NOT-POSSIBLE: $this->assertEquals($this->adapter->getCurrentSchema(), $md['filesize']->getSchemaName());
            // INSTEAD USE
            $this->assertEquals(null, $md['filesize']->getSchemaName());


            $this->assertEquals(Column\Type::TYPE_INTEGER, $md['container_id']->getDatatype());
            // PDO-NOT-POSSIBLE: $this->assertEquals('media', $md['container_id']->getTableName());
            $this->assertEquals('m', $md['container_id']->getTableAlias());


            $this->assertEquals(Column\Type::TYPE_INTEGER, $md['mcid']->getDatatype());
            // PDO-NOT-POSSIBLE: $this->assertEquals('media_container', $md['mcid']->getTableName());
            $this->assertEquals('mc', $md['mcid']->getTableAlias());


            $this->assertEquals(Column\Type::TYPE_INTEGER, $md['max(filemtime)']->getDatatype());
            $this->assertEquals(Column\Type::TYPE_INTEGER, $md['max_time']->getDatatype());
            $this->assertEquals('INTEGER', $md['max_time']->getNativeDatatype());

            // Testing computed
            $this->assertTrue($md['min_time']->isComputed());
            $this->assertTrue($md['max_time']->isComputed());
            $this->assertTrue($md['avg_time']->isComputed());
            $this->assertTrue($md['files']->isComputed());
            $this->assertTrue($md['test_string']->isComputed());
            $this->assertTrue($md['test_float']->isComputed());
            $this->assertTrue($md['test_calc']->isComputed());
            $this->assertTrue($md['test_calc_2']->isComputed());
            $this->assertFalse($md['container_id']->isComputed());

            // TESTING Aliased

            $this->assertEquals('mcid', $md['mcid']->getAlias());
            // PDO-NOT-POSSIBLE: $this->assertEquals('container_id', $md['mcid']->getName());
            $this->assertEquals('min_time', $md['min_time']->getName());
            $this->assertEquals('min_time', $md['min_time']->getAlias());

            // TEST if column is part of a group
            // PDO-NOT-POSSIBLE: $this->assertTrue($md['count_media']->isGroup());
            // PDO-NOT-POSSIBLE: $this->assertTrue($md['min_time']->isGroup());
            // PDO-NOT-POSSIBLE: $this->assertTrue($md['max_time']->isGroup());
            // PDO-NOT-POSSIBLE: $this->assertTrue($md['min(filemtime)']->isGroup());
            // PDO-NOT-POSSIBLE: $this->assertTrue($md['max(filemtime)']->isGroup());
            // WARNING BUGS IN MYSQL (should be true)
            // PDO-NOT-POSSIBLE: $this->assertFalse($md['avg(filemtime)']->isGroup());
            // PDO-NOT-POSSIBLE: $this->assertFalse($md['avg_time']->isGroup());
            // PDO-NOT-POSSIBLE: $this->assertFalse($md['files']->isGroup());
            // PDO-NOT-POSSIBLE: $this->assertFalse($md['group_concat(filename)']->isGroup());
            // Various type returned by using functions
            $this->assertEquals(Column\Type::TYPE_INTEGER, $md['count_media']->getDatatype());
            $this->assertEquals(Column\Type::TYPE_INTEGER, $md['max_time']->getDatatype());
            $this->assertEquals(Column\Type::TYPE_INTEGER, $md['min_time']->getDatatype());
            $this->assertEquals(Column\Type::TYPE_DECIMAL, $md['avg_time']->getDatatype());
        } else {
            $this->markTestSkipped('Only valid for PHP 5.4+ version');
        }
    }


    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }
}
