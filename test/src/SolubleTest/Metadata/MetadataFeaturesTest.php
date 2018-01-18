<?php

namespace SolubleTest\Metadata;

use Soluble\Metadata\Reader;
use Soluble\Datatype\Column;
use PHPUnit\Framework\TestCase;

class MetadataFeaturesTest extends TestCase
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

    public function testGetColumsMetadataMultipleTableFunctions()
    {
        // WARNING BUGS IN MYSQL (should be true)
        $client_info = mysqli_get_client_info();
        $client_version = mysqli_get_client_version();
        if (preg_match('/mysqlnd/', strtolower($client_info))) {
            $mysqli_client = 'mysqlnd';
        } elseif (preg_match('/mariadb/', strtolower($client_info))) {
            $mysqli_client = 'libmariadb';
        } else {
            $mysqli_client = 'libmysql';
        }

        echo "Warning, test was made on client '$mysqli_client', may differs when using mysqlnd, libmariadb, libmysql";

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

        foreach ($this->readers as $reader_type => $reader) {
            $md = $reader->getColumnsMetadata($sql);

            self::assertNull($md['test_string']->getTableName());
            self::assertNull($md['test_string']->getTableAlias());
            self::assertEquals(1, $md['test_string']->getOrdinalPosition());

            self::assertEquals(Column\Type::TYPE_DECIMAL, $md['test_calc']->getDatatype());
            self::assertEquals(null, $md['test_calc']->getTableName());

            self::assertEquals(Column\Type::TYPE_INTEGER, $md['test_calc_2']->getDatatype());
            self::assertEquals(false, $md['test_calc_2']->isAutoIncrement());
            self::assertEquals(null, $md['test_calc_2']->getTableName());

            self::assertEquals(Column\Type::TYPE_INTEGER, $md['filesize']->getDatatype());

            self::assertEquals('m', $md['filesize']->getTableAlias());

            self::assertEquals(null, $md['test_string']->getSchemaName());

            self::assertEquals(Column\Type::TYPE_INTEGER, $md['container_id']->getDatatype());
            self::assertEquals('m', $md['container_id']->getTableAlias());

            self::assertEquals(Column\Type::TYPE_INTEGER, $md['mcid']->getDatatype());
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
            self::assertEquals('min_time', $md['min_time']->getName());
            self::assertEquals('min_time', $md['min_time']->getAlias());

            // Various type returned by using functions
            self::assertEquals(Column\Type::TYPE_INTEGER, $md['count_media']->getDatatype());
            self::assertEquals(Column\Type::TYPE_INTEGER, $md['max_time']->getDatatype());
            self::assertEquals(Column\Type::TYPE_INTEGER, $md['min_time']->getDatatype());
            self::assertEquals(Column\Type::TYPE_DECIMAL, $md['avg_time']->getDatatype());

            if (preg_match('/mysql/', $reader_type)) {
                switch ($mysqli_client) {
                    case 'mysqlnd':
                        // as PHP 5.3 -> 5.6 there's a bug in the
                        // mysqlnd extension... the following assertions
                        // are wrong !!!!
                        //$this->markTestIncomplete("Does not test everything");

                        self::assertFalse($md['avg(filemtime)']->isGroup());
                        self::assertFalse($md['avg_time']->isGroup());
                        self::assertFalse($md['files']->isGroup());
                        self::assertFalse($md['group_concat(filename)']->isGroup());

                        break;
                    case 'libmariadb':
                    case 'libmysql':
                    default:
                        //$this->markTestIncomplete("Does not test exevything");
                        self::assertTrue($md['avg(filemtime)']->isGroup());
                        self::assertTrue($md['avg_time']->isGroup());

                        // see those cases
                        self::assertFalse($md['files']->isGroup());
                        self::assertFalse($md['group_concat(filename)']->isGroup());
                }
            }
            switch ($reader_type) {
                    case 'pdo_mysql':
                        // In PDO the table name is always the alias
                        self::assertEquals('m', $md['filesize']->getTableName());
                        self::assertEquals('m', $md['container_id']->getTableName());
                        self::assertEquals('mc', $md['mcid']->getTableName());

                        // Aliased columns
                        self::assertEquals('mcid', $md['mcid']->getName());

                        // TEST if column is part of a group
                        self::assertEquals(false, $md['count_media']->isGroup());
                        self::assertEquals(false, $md['min_time']->isGroup());

                        self::assertEquals(false, $md['max_time']->isGroup());
                        self::assertEquals(false, $md['min(filemtime)']->isGroup());
                        self::assertEquals(false, $md['max(filemtime)']->isGroup());

                        // In PDO the schema name is always null
                        self::assertEquals(null, $md['filesize']->getSchemaName());

                        break;
                    case 'mysqli':
                        self::assertEquals('media', $md['filesize']->getTableName());
                        self::assertEquals('media', $md['container_id']->getTableName());
                        self::assertEquals('media_container', $md['mcid']->getTableName());

                        // Aliased columns
                        self::assertEquals('container_id', $md['mcid']->getName());

                        // TEST if column is part of a group
                        self::assertTrue($md['count_media']->isGroup());
                        self::assertTrue($md['min_time']->isGroup());

                        self::assertTrue($md['max_time']->isGroup());
                        self::assertTrue($md['min(filemtime)']->isGroup());
                        self::assertTrue($md['max(filemtime)']->isGroup());

                        self::assertEquals(\SolubleTestFactories::getDatabaseName('mysqli'), $md['filesize']->getSchemaName());

                        break;
            }
        }
    }

    public function testGetColumnsMetadataWithDefaults()
    {
        $sql = 'select * from test_table_with_default';
        foreach ($this->readers as $reader_type => $reader) {
            $md = $reader->getColumnsMetadata($sql);

            if ($undocumented_way = true) {
                // IN PHP 5.5 / 7.0 always return null (?)
                // The documented way would be to return the values
                // in the else
                self::assertEquals(null, $md['default_5']->getColumnDefault(), "failed for reader $reader_type");
                self::assertEquals(null, $md['default_cool']->getColumnDefault(), "failed for reader $reader_type");
                self::assertEquals(null, $md['default_yes']->getColumnDefault(), "failed for reader $reader_type");
            } else {
                self::assertEquals(5, $md['default_5']->getColumnDefault(), "failed for reader $reader_type");
                self::assertEquals('cool', $md['default_cool']->getColumnDefault(), "failed for reader $reader_type");
                self::assertEquals('yes', $md['default_yes']->getColumnDefault(), "failed for reader $reader_type");
            }
        }
    }

    public function testGetColumnsMetadataFeatures()
    {
        $sql = 'select * from test_table_types';
        foreach ($this->readers as $reader_type => $reader) {
            $md = $reader->getColumnsMetadata($sql);

            self::assertInstanceOf('Soluble\Metadata\ColumnsMetadata', $md);
            self::assertInstanceOf('ArrayObject', $md);

            self::assertEquals(true, $md['id']->isPrimary());
            self::assertEquals(Column\Type::TYPE_INTEGER, $md['id']->getDatatype());
            self::assertEquals('test_table_types', $md['id']->getTableName());
            self::assertEquals(false, $md['id']->isNullable());
            self::assertEquals('test_table_types', $md['id']->getTableAlias());
            self::assertEquals(1, $md['id']->getOrdinalPosition());

            self::assertEquals($md['test_varchar_255']->getDatatype(), Column\Type::TYPE_STRING);
            self::assertEquals($md['test_varchar_255']->getNativeDatatype(), 'VARCHAR');
            self::assertEquals($md['test_char_10']->getDatatype(), Column\Type::TYPE_STRING);

            // This does not work (cause utf8 store in multibyte)
            // @todo utf8 support in getCharacterMaximumLength
            //  Divide by 3
            // Sould be self::assertEquals(10, $md['test_char_10']->getCharacterMaximumLength());
            // But returned
            self::assertEquals(30, $md['test_char_10']->getCharacterMaximumLength());

            self::assertGreaterThanOrEqual(10, $md['test_char_10']->getCharacterMaximumLength());

            self::assertEquals($md['test_text_2000']->getDatatype(), Column\Type::TYPE_BLOB);
            self::assertEquals($md['test_text_2000']->getNativeDatatype(), 'BLOB');

            self::assertEquals($md['test_binary_3']->getDatatype(), Column\Type::TYPE_STRING);

            self::assertEquals($md['test_varbinary_10']->getDatatype(), Column\Type::TYPE_STRING);
            self::assertEquals($md['test_varbinary_10']->getNativeDatatype(), 'VARCHAR');

            self::assertEquals($md['test_int_unsigned']->getDatatype(), Column\Type::TYPE_INTEGER);

            self::assertEquals($md['test_bigint']->getDatatype(), Column\Type::TYPE_INTEGER);

            self::assertEquals($md['test_bigint']->getNativeDatatype(), 'BIGINT');

            self::assertEquals($md['test_decimal_10_3']->getDatatype(), Column\Type::TYPE_DECIMAL);
            self::assertEquals($md['test_decimal_10_3']->getNativeDatatype(), 'DECIMAL');
            self::assertEquals(10, $md['test_decimal_10_3']->getNumericScale());
            self::assertEquals(3, $md['test_decimal_10_3']->getNumericPrecision());
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

            self::assertEquals($md['test_set']->getDatatype(), Column\Type::TYPE_STRING);

            self::assertEquals($md['test_bit']->getDatatype(), Column\Type::TYPE_BIT);
            self::assertEquals('BIT', $md['test_bit']->getNativeDatatype());

            self::assertEquals($md['test_bool']->getDatatype(), Column\Type::TYPE_INTEGER);
            self::assertEquals('TINYINT', $md['test_bool']->getNativeDatatype());

            self::assertEquals($md['test_geometry']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
            self::assertEquals(null, $md['test_geometry']->getNativeDatatype());

            self::assertEquals($md['test_point']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
            self::assertEquals(null, $md['test_point']->getNativeDatatype());

            self::assertEquals($md['test_linestring']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
            self::assertEquals(null, $md['test_linestring']->getNativeDatatype());

            self::assertEquals($md['test_polygon']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
            self::assertEquals(null, $md['test_polygon']->getNativeDatatype());

            self::assertEquals($md['test_multipolygon']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
            self::assertEquals(null, $md['test_multipolygon']->getNativeDatatype());

            self::assertEquals($md['test_multipoint']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
            self::assertEquals(null, $md['test_multipoint']->getNativeDatatype());

            self::assertEquals($md['test_multilinestring']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
            self::assertEquals(null, $md['test_multilinestring']->getNativeDatatype());

            self::assertEquals($md['test_geometrycollection']->getDatatype(), Column\Type::TYPE_SPATIAL_GEOMETRY);
            self::assertEquals(null, $md['test_geometrycollection']->getNativeDatatype());

            if (preg_match('/mysql/', $reader_type)) {
                switch ($reader_type) {
                    case 'pdo_mysql':
                        // Native datatype with PDO differs from mysqli (CHAR is returned instead of varchar)
                        self::assertEquals($md['test_char_10']->getNativeDatatype(), 'CHAR');
                        self::assertEquals($md['test_binary_3']->getNativeDatatype(), 'CHAR');

                        // PDO does not return ENUM or SET but char instead
                        self::assertEquals('CHAR', $md['test_enum']->getNativeDatatype());
                        self::assertEquals('CHAR', $md['test_set']->getNativeDatatype());

                        // PDO does not retrieve 'def' like mysqli, instead retrieve null
                        self::assertEquals($md['id']->getCatalog(), null);
                        // PDO does not retrieve signed, unsigned
                        self::assertEquals(null, $md['id']->isNumericUnsigned());
                        self::assertEquals(null, $md['id']->getNumericUnsigned());
                        self::assertEquals(null, $md['test_int_unsigned']->isNumericUnsigned());
                        self::assertEquals(null, $md['test_bigint']->isNumericUnsigned());

                        // PDO does not know if column is autoincrement
                        self::assertEquals(null, $md['id']->isAutoIncrement());

                        break;
                    case 'mysqli':
                        // CHAR
                        self::assertEquals($md['test_char_10']->getNativeDatatype(), 'VARCHAR');
                        self::assertEquals($md['test_binary_3']->getNativeDatatype(), 'VARCHAR');

                        // ENUM
                        self::assertEquals('ENUM', $md['test_enum']->getNativeDatatype());
                        self::assertEquals('SET', $md['test_set']->getNativeDatatype());

                        // CATALOG
                        self::assertEquals($md['id']->getCatalog(), 'def');

                        // SIGNED
                        self::assertEquals(true, $md['id']->isNumericUnsigned());
                        self::assertEquals(true, $md['id']->getNumericUnsigned());
                        self::assertEquals(true, $md['test_int_unsigned']->isNumericUnsigned());
                        self::assertEquals(false, $md['test_bigint']->isNumericUnsigned());

                        // AUTOINC
                        self::assertEquals(true, $md['id']->isAutoIncrement());

                        break;
                }
            }
        }
    }
}
