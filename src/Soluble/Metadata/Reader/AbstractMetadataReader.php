<?php

declare(strict_types=1);

namespace Soluble\Metadata\Reader;

use Soluble\Metadata\ColumnsMetadata;
use Soluble\Metadata\Exception;

abstract class AbstractMetadataReader
{
    /**
     * Keep static cache in memory.
     *
     * @var bool
     */
    protected $cache_active = true;

    /**
     * @var array
     */
    protected static $metadata_cache = [];

    /**
     * @param bool $active
     *
     * @return AbstractMetadataReader
     */
    public function setStaticCache(bool $active = true)
    {
        $this->cache_active = $active;

        return $this;
    }

    /**
     * Return columns metadata from query.
     *
     * @throws Exception\UnsupportedTypeException
     * @throws Exception\AmbiguousColumnException
     * @throws Exception\InvalidQueryException
     */
    public function getColumnsMetadata(string $sql): ColumnsMetadata
    {
        if ($this->cache_active) {
            $cache_key = md5($sql);
            if (!array_key_exists($cache_key, static::$metadata_cache)) {
                $md = $this->readColumnsMetadata($sql);
                static::$metadata_cache[$cache_key] = $md;
            }

            return static::$metadata_cache[$cache_key];
        }

        return $this->readColumnsMetadata($sql);
    }

    /**
     * Return columns metadata from a table.
     *
     * @throws Exception\UnsupportedTypeException
     * @throws Exception\AmbiguousColumnException
     * @throws Exception\TableNotFoundException
     */
    public function getTableMetadata(string $table): ColumnsMetadata
    {
        try {
            $metadata = $this->getColumnsMetadata(sprintf('select * from %s', $table));
        } catch (Exception\InvalidQueryException $e) {
            throw new Exception\TableNotFoundException(sprintf(
                'Table "%s" does not exists (%s).',
                $table,
                $e->getMessage()
            ));
        }

        return $metadata;
    }

    /**
     * Read metadata information from source.
     *
     * @throws Exception\UnsupportedTypeException
     * @throws Exception\AmbiguousColumnException
     * @throws \Soluble\Metadata\Exception\InvalidQueryException
     */
    abstract protected function readColumnsMetadata(string $sql): ColumnsMetadata;

    /**
     * Optimization, will add false condition to the query
     * so the metadata loading will be faster.
     */
    protected function getEmptiedQuery(string $sql): string
    {
        // see the reason why in Vision_Store_Adapter_ZendDbSelect::getMetaData
        //$sql = str_replace("('__innerselect'='__innerselect')", '(1=0)', $sql);

        $sql = preg_replace('/(\r\n|\r|\n|\t)+/', ' ', strtolower($sql));
        $sql = trim($sql);
        $sql = preg_replace('/\s+/', ' ', $sql);

        $replace_regexp = "LIMIT[\s]+[\d]+((\s*,\s*\d+)|(\s+OFFSET\s+\d+)){0,1}";

        $search_regexp = "$replace_regexp";
        if (!preg_match("/$search_regexp/i", $sql)) {
            // Limit is not already present
            $sql .= ' LIMIT 0';
        } else {
            // replace first if offset exists, then if not
            //preg_match_all("/($search_regexp)/i", $sql, $matches, PREG_PATTERN_ORDER);
            //var_dump($matches);

            $sql = preg_replace("/($replace_regexp)/i", 'LIMIT 0', $sql);
        }

        return $sql;
    }
}
