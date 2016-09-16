<?php

namespace SolubleTest\Metadata;

use Soluble\Metadata\Reader;
use Soluble\Datatype\Column;

class MetadataFeaturesTest extends \PHPUnit_Framework_TestCase
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

    public function testGetColumn()
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
            $mysqli_client = "libmariadb";
        } else {
            $mysqli_client = "libmysql";
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

            $this->assertEquals(null, $md['test_string']->getTableName());
            $this->assertEquals(null, $md['test_string']->getTableAlias());
            $this->assertEquals(1, $md['test_string']->getOrdinalPosition());

            $this->assertEquals(Column\Type::TYPE_DECIMAL, $md['test_calc']->getDatatype());
            $this->assertEquals(null, $md['test_calc']->getTableName());

            $this->assertEquals(Column\Type::TYPE_INTEGER, $md['test_calc_2']->getDatatype());
            $this->assertEquals(false, $md['test_calc_2']->isAutoIncrement());
            $this->assertEquals(null, $md['test_calc_2']->getTableName());

            $this->assertEquals(Column\Type::TYPE_INTEGER, $md['filesize']->getDatatype());

            $this->assertEquals('m', $md['filesize']->getTableAlias());


            $this->assertEquals(null, $md['test_string']->getSchemaName());

            $this->assertEquals(Column\Type::TYPE_INTEGER, $md['container_id']->getDatatype());
            $this->assertEquals('m', $md['container_id']->getTableAlias());


            $this->assertEquals(Column\Type::TYPE_INTEGER, $md['mcid']->getDatatype());
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
            $this->assertEquals('min_time', $md['min_time']->getName());
            $this->assertEquals('min_time', $md['min_time']->getAlias());



            // Various type returned by using functions
            $this->assertEquals(Column\Type::TYPE_INTEGER, $md['count_media']->getDatatype());
            $this->assertEquals(Column\Type::TYPE_INTEGER, $md['max_time']->getDatatype());
            $this->assertEquals(Column\Type::TYPE_INTEGER, $md['min_time']->getDatatype());
            $this->assertEquals(Column\Type::TYPE_DECIMAL, $md['avg_time']->getDatatype());

            if (preg_match('/mysql/', $reader_type)) {
                switch ($mysqli_client) {

                    case 'mysqlnd':
                        // as PHP 5.3 -> 5.6 there's a bug in the
                        // mysqlnd extension... the following assertions
                        // are wrong !!!!
                        //$this->markTestIncomplete("Does not test everything");

                        $this->assertFalse($md['avg(filemtime)']->isGroup());
                        $this->assertFalse($md['avg_time']->isGroup());
                        $this->assertFalse($md['files']->isGroup());
                        $this->assertFalse($md['group_concat(filename)']->isGroup());


                        break;
                    case 'libmariadb':
                    case 'libmysql':
                    default:
                        //$this->markTestIncomplete("Does not test exevything");
                        $this->assertTrue($md['avg(filemtime)']->isGroup());
                        $this->assertTrue($md['avg_time']->isGroup());

                        // see those cases
                        $this->assertFalse($md['files']->isGroup());
                        $this->assertFalse($md['group_concat(filename)']->isGroup());
                }
            }
            switch ($reader_type) {
                    case 'pdo_mysql' :
                        // In PDO the table name is always the alias
                        $this->assertEquals('m', $md['filesize']->getTableName());
                        $this->assertEquals('m', $md['container_id']->getTableName());
                        $this->assertEquals('mc', $md['mcid']->getTableName());

                        // Aliased columns
                        $this->assertEquals('mcid', $md['mcid']->getName());


                        // TEST if column is part of a group
                        $this->assertEquals(false, $md['count_media']->isGroup());
                        $this->assertEquals(false, $md['min_time']->isGroup());

                        $this->assertEquals(false, $md['max_time']->isGroup());
                        $this->assertEquals(false, $md['min(filemtime)']->isGroup());
                        $this->assertEquals(false, $md['max(filemtime)']->isGroup());


                        // In PDO the schema name is always null
                        $this->assertEquals(null, $md['filesize']->getSchemaName());


                        break;
                    case 'mysqli':
                        $this->assertEquals('media', $md['filesize']->getTableName());
                        $this->assertEquals('media', $md['container_id']->getTableName());
                        $this->assertEquals('media_container', $md['mcid']->getTableName());

                        // Aliased columns
                        $this->assertEquals('container_id', $md['mcid']->getName());

                        // TEST if column is part of a group
                        $this->assertTrue($md['count_media']->isGroup());
                        $this->assertTrue($md['min_time']->isGroup());

                        $this->assertTrue($md['max_time']->isGroup());
                        $this->assertTrue($md['min(filemtime)']->isGroup());
                        $this->assertTrue($md['max(filemtime)']->isGroup());


                        $this->assertEquals(\SolubleTestFactories::getDatabaseName('mysqli'), $md['filesize']->getSchemaName());

                        break;
            }
        }
    }


    public function testGetColumnsMetadataWithDefaults()
    {
        $sql = "select * from test_table_with_default";
        foreach ($this->readers as $reader_type => $reader) {
            $md = $reader->getColumnsMetadata($sql);


            if ($undocumented_way=true) {
                // IN PHP 5.5 / 7.0 always return null (?)
                // The documented way would be to return the values 
                // in the else
                $this->assertEquals(null, $md['default_5']->getColumnDefault(), "failed for reader $reader_type");
                $this->assertEquals(null, $md['default_cool']->getColumnDefault(), "failed for reader $reader_type");
                $this->assertEquals(null, $md['default_yes']->getColumnDefault(), "failed for reader $reader_type");
            } else {
                $this->assertEquals(5, $md['default_5']->getColumnDefault(), "failed for reader $reader_type");
                $this->assertEquals('cool', $md['default_cool']->getColumnDefault(), "failed for reader $reader_type");
                $this->assertEquals('yes', $md['default_yes']->getColumnDefault(), "failed for reader $reader_type");
            }
        }
    }



    public function testGetColumnsMetadataFeatures()
    {
        $sql = "select * from test_table_types";
        foreach ($this->readers as $reader_type => $reader) {
            $md = $reader->getColumnsMetadata($sql);

            $this->assertInstanceOf('Soluble\Metadata\ColumnsMetadata', $md);
            $this->assertInstanceOf('ArrayObject', $md);

            $this->assertEquals(true, $md['id']->isPrimary());
            $this->assertEquals(Column\Type::TYPE_INTEGER, $md['id']->getDatatype());
            $this->assertEquals('test_table_types', $md['id']->getTableName());
            $this->assertEquals(false, $md['id']->isNullable());
            $this->assertEquals('test_table_types', $md['id']->getTableAlias());
            $this->assertEquals(1, $md['id']->getOrdinalPosition());

            $this->assertEquals($md['test_varchar_255']->getDatatype(), Column\Type::TYPE_STRING);
            $this->assertEquals($md['test_varchar_255']->getNativeDatatype(), 'VARCHAR');
            $this->assertEquals($md['test_char_10']->getDatatype(), Column\Type::TYPE_STRING);


            // This does not work (cause utf8 store in multibyte)
            // @todo utf8 support in getCharacterMaximumLength
            //  Divide by 3
            // Sould be $this->assertEquals(10, $md['test_char_10']->getCharacterMaximumLength());
            // But returned
            $this->assertEquals(30, $md['test_char_10']->getCharacterMaximumLength());

            $this->assertGreaterThanOrEqual(10, $md['test_char_10']->getCharacterMaximumLength());

            $this->assertEquals($md['test_text_2000']->getDatatype(), Column\Type::TYPE_BLOB);
            $this->assertEquals($md['test_text_2000']->getNativeDatatype(), 'BLOB');

            $this->assertEquals($md['test_binary_3']->getDatatype(), Column\Type::TYPE_STRING);


            $this->assertEquals($md['test_varbinary_10']->getDatatype(), Column\Type::TYPE_STRING);
            $this->assertEquals($md['test_varbinary_10']->getNativeDatatype(), 'VARCHAR');

            $this->assertEquals($md['test_int_unsigned']->getDatatype(), Column\Type::TYPE_INTEGER);


            $this->assertEquals($md['test_bigint']->getDatatype(), Column\Type::TYPE_INTEGER);

            $this->assertEquals($md['test_bigint']->getNativeDatatype(), 'BIGINT');

            $this->assertEquals($md['test_decimal_10_3']->getDatatype(), Column\Type::TYPE_DECIMAL);
            $this->assertEquals($md['test_decimal_10_3']->getNativeDatatype(), 'DECIMAL');
            $this->assertEquals(10, $md['test_decimal_10_3']->getNumericScale());
            $this->assertEquals(3, $md['test_decimal_10_3']->getNumericPrecision());
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

            $this->assertEquals($md['test_set']->getDatatype(), Column\Type::TYPE_STRING);

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


            if (preg_match('/mysql/', $reader_type)) {
                switch ($reader_type) {
                    case 'pdo_mysql' :
                        // Native datatype with PDO differs from mysqli (CHAR is returned instead of varchar)
                        $this->assertEquals($md['test_char_10']->getNativeDatatype(), 'CHAR');
                        $this->assertEquals($md['test_binary_3']->getNativeDatatype(), 'CHAR');

                        // PDO does not return ENUM or SET but char instead
                        $this->assertEquals('CHAR', $md['test_enum']->getNativeDatatype());
                        $this->assertEquals('CHAR', $md['test_set']->getNativeDatatype());

                        // PDO does not retrieve 'def' like mysqli, instead retrieve null
                        $this->assertEquals($md['id']->getCatalog(), null);
                        // PDO does not retrieve signed, unsigned
                        $this->assertEquals(null, $md['id']->isNumericUnsigned());
                        $this->assertEquals(null, $md['id']->getNumericUnsigned());
                        $this->assertEquals(null, $md['test_int_unsigned']->isNumericUnsigned());
                        $this->assertEquals(null, $md['test_bigint']->isNumericUnsigned());

                        // PDO does not know if column is autoincrement
                        $this->assertEquals(null, $md['id']->isAutoIncrement());

                        break;
                    case 'mysqli':
                        // CHAR
                        $this->assertEquals($md['test_char_10']->getNativeDatatype(), 'VARCHAR');
                        $this->assertEquals($md['test_binary_3']->getNativeDatatype(), 'VARCHAR');

                        // ENUM
                        $this->assertEquals('ENUM', $md['test_enum']->getNativeDatatype());
                        $this->assertEquals('SET', $md['test_set']->getNativeDatatype());

                        // CATALOG
                        $this->assertEquals($md['id']->getCatalog(), 'def');

                        // SIGNED
                        $this->assertEquals(true, $md['id']->isNumericUnsigned());
                        $this->assertEquals(true, $md['id']->getNumericUnsigned());
                        $this->assertEquals(true, $md['test_int_unsigned']->isNumericUnsigned());
                        $this->assertEquals(false, $md['test_bigint']->isNumericUnsigned());

                        // AUTOINC
                        $this->assertEquals(true, $md['id']->isAutoIncrement());

                        break;
                }
            }
        }
    }
}
