<?php

namespace SolubleTest\Metadata\Reader;

use Soluble\Metadata\ColumnsMetadata;
use Soluble\Metadata\Exception\AmbiguousColumnException;
use Soluble\Metadata\Exception\EmptyQueryException;
use Soluble\Metadata\Exception\InvalidQueryException;
use Soluble\Metadata\Reader\MysqliMetadataReader;
use Soluble\Datatype\Column;
use Soluble\DbWrapper\Adapter\MysqliAdapter;
use PHPUnit\Framework\TestCase;

class MysqliMetadataReaderTest extends TestCase
{
    /**
     * @var MysqliMetadataReader
     */
    protected $metadata;

    /**
     * @var MysqliAdapter
     */
    protected $adapter;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->adapter = new MysqliAdapter(\SolubleTestFactories::getDbConnection('mysqli'));
        $this->metadata = new MysqliMetadataReader($this->adapter->getConnection()->getResource());
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    public function testGetColumnsMetadataThrowsEmptyQueryException()
    {
        self::expectException(EmptyQueryException::class);
        $sql = '';
        $this->metadata->getColumnsMetadata($sql);
    }

    public function testGetColumnsMetadataThrowsInvalidQueryException()
    {
        self::expectException(InvalidQueryException::class);
        $sql = 'select * from sss';
        $this->metadata->getColumnsMetadata($sql);
    }

    public function testGetColumnsMetadataNonCached()
    {
        $sql = 'select id from test_table_types';
        $this->metadata->setStaticCache(false);
        $md = $this->metadata->getColumnsMetadata($sql);
        $this->metadata->setStaticCache(true);
        self::assertInstanceOf(ColumnsMetadata::class, $md);
    }

    public function testGetColumnsMetadataThrowsAmbiguousColumnException()
    {
        self::expectException(AmbiguousColumnException::class);
        $sql = 'select id, test_char_10 as id from test_table_types';
        $md = $this->metadata->getColumnsMetadata($sql);
    }

    public function testGetColumnsMetadata()
    {
        $sql = 'select * from test_table_types';
        $md = $this->metadata->getColumnsMetadata($sql);
        self::assertInstanceOf(ColumnsMetadata::class, $md);

        self::assertEquals($md['id']->isPrimary(), true);
        self::assertEquals($md['id']->getDatatype(), Column\Type::TYPE_INTEGER);
        self::assertEquals($md['id']->getTableName(), 'test_table_types');
        self::assertEquals($md['id']->isNullable(), false);
        self::assertEquals($md['id']->getTableAlias(), 'test_table_types');
        self::assertEquals($md['id']->getOrdinalPosition(), 1);
        self::assertEquals($md['id']->getCatalog(), 'def');
        self::assertEquals($md['id']->isAutoIncrement(), true);
        self::assertTrue($md['id']->isNumericUnsigned());
        self::assertTrue($md['id']->getNumericUnsigned());

        self::assertEquals($md['test_varchar_255']->getDatatype(), Column\Type::TYPE_STRING);
        self::assertEquals($md['test_varchar_255']->getNativeDatatype(), 'VARCHAR');
        self::assertEquals($md['test_char_10']->getDatatype(), Column\Type::TYPE_STRING);
        self::assertEquals($md['test_char_10']->getNativeDatatype(), 'VARCHAR');

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
        self::assertEquals($md['test_binary_3']->getNativeDatatype(), 'VARCHAR');

        self::assertEquals($md['test_varbinary_10']->getDatatype(), Column\Type::TYPE_STRING);
        self::assertEquals($md['test_varbinary_10']->getNativeDatatype(), 'VARCHAR');

        self::assertEquals($md['test_int_unsigned']->getDatatype(), Column\Type::TYPE_INTEGER);
        self::assertTrue($md['test_int_unsigned']->isNumericUnsigned());

        self::assertEquals($md['test_bigint']->getDatatype(), Column\Type::TYPE_INTEGER);
        self::assertFalse($md['test_bigint']->isNumericUnsigned());
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
        self::assertEquals('ENUM', $md['test_enum']->getNativeDatatype());

        self::assertEquals($md['test_set']->getDatatype(), Column\Type::TYPE_STRING);
        self::assertEquals('SET', $md['test_set']->getNativeDatatype());

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

    public function testGetColumnsMetadataWithDefaults()
    {
        $sql = 'select * from test_table_with_default';
        $md = $this->metadata->getColumnsMetadata($sql);

        if (true) {
            self::assertEmpty($md['default_5']->getColumnDefault());
            self::assertEmpty($md['default_cool']->getColumnDefault());
            self::assertEmpty($md['default_yes']->getColumnDefault());
        } else {
            self::assertEquals(5, $md['default_5']->getColumnDefault());
            self::assertEquals('cool', $md['default_cool']->getColumnDefault());
            self::assertEquals('yes', $md['default_yes']->getColumnDefault());
        }
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
                        null as test_null,
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

        $md = $this->metadata->getColumnsMetadata($sql);

        self::assertFalse($md['test_string']->isPrimary());
        self::assertEquals(Column\Type::TYPE_STRING, $md['test_string']->getDatatype());
        self::assertNull($md['test_string']->getTableName());
        self::assertFalse($md['test_string']->isNullable());
        self::assertNull($md['test_string']->getTableAlias());
        self::assertEquals(1, $md['test_string']->getOrdinalPosition());
        self::assertEquals('def', $md['test_string']->getCatalog());

        self::assertEquals(Column\Type::TYPE_DECIMAL, $md['test_calc']->getDatatype());
        self::assertNull($md['test_calc']->getTableName());

        self::assertEquals(Column\Type::TYPE_INTEGER, $md['test_calc_2']->getDatatype());
        self::assertFalse($md['test_calc_2']->isAutoIncrement());
        self::assertNull($md['test_calc_2']->getTableName());

        self::assertEquals(Column\Type::TYPE_INTEGER, $md['filesize']->getDatatype());
        self::assertEquals('media', $md['filesize']->getTableName());
        self::assertEquals('m', $md['filesize']->getTableAlias());

        self::assertNull($md['test_string']->getSchemaName());
        self::assertEquals($this->adapter->getConnection()->getCurrentSchema(), $md['filesize']->getSchemaName());

        self::assertEquals(Column\Type::TYPE_INTEGER, $md['container_id']->getDatatype());
        self::assertEquals('media', $md['container_id']->getTableName());
        self::assertEquals('m', $md['container_id']->getTableAlias());

        self::assertEquals(Column\Type::TYPE_INTEGER, $md['mcid']->getDatatype());
        self::assertEquals('media_container', $md['mcid']->getTableName());
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
        self::assertEquals('container_id', $md['mcid']->getName());
        self::assertEquals('min_time', $md['min_time']->getName());
        self::assertEquals('min_time', $md['min_time']->getAlias());

        // TEST if column is part of a group
        self::assertTrue($md['count_media']->isGroup());
        self::assertTrue($md['min_time']->isGroup());

        self::assertTrue($md['max_time']->isGroup());
        self::assertTrue($md['min(filemtime)']->isGroup());
        self::assertTrue($md['max(filemtime)']->isGroup());

        // Various type returned by using functions
        self::assertEquals(Column\Type::TYPE_INTEGER, $md['count_media']->getDatatype());
        self::assertEquals(Column\Type::TYPE_INTEGER, $md['max_time']->getDatatype());
        self::assertEquals(Column\Type::TYPE_INTEGER, $md['min_time']->getDatatype());
        self::assertEquals(Column\Type::TYPE_DECIMAL, $md['avg_time']->getDatatype());

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

        switch ($mysqli_client) {
            case 'mysqlnd':
                // as PHP 5.3 -> 5.6 there's a bug in the
                // mysqlnd extension... the following assertions
                // are wrong !!!!
                //$this->markTestIncomplete("Does not test exevything");

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

        $this->markTestIncomplete("Warning, test was made on client '$mysqli_client', may differs when using mysqlnd, libmariadb, libmysql");
    }

    public function testGetEmptyQuery()
    {
        $queries = [
            'select 1, 2',

            'select media_id from media',
            'select media_id from media limit 1 offset 2',
            'SELECT * from product limit 10',
            'select * from product limit 0  offset 10',
            'select * from product limit 0, 10',
            'select 1 limit 10',
            'select media_id from media
                 LimiT   10',
        ];

        $mysqli = $this->adapter->getConnection()->getResource();

        foreach ($queries as $idx => $query) {
            $sql = $this->invokeMethod($this->metadata, 'getEmptiedQuery', [$query]);

            $stmt = $mysqli->prepare($sql);

            if (!$stmt) {
                $message = $mysqli->error;
                throw new \Exception("Sql is not correct : $message, ($sql)");
            }

            $stmt->execute();
            $stmt->store_result();
            $num_rows = $stmt->num_rows;

            self::assertInternalType('int', $num_rows);
            self::assertEquals(0, $num_rows, "Emptied query $idx : $sql ");
            $stmt->close();
        }
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object $object     instantiated object that we will run method on
     * @param string $methodName Method name to call
     * @param array  $parameters array of parameters to pass into method
     *
     * @return mixed method return
     */
    public function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
