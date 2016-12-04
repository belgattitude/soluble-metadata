<?php

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
     * @param bool $active
     *
     * @return AbstractMetadataReader
     */
    public function setStaticCache($active = true)
    {
        $this->cache_active = $active;

        return $this;
    }

    /**
     * Return columns metadata from query.
     *
     * @throws UnsupportedDatatypeException
     * @throws Exception\AmbiguousColumnException
     *
     * @param string $sql
     *
     * @return ColumnsMetadata
     */
    public function getColumnsMetadata($sql)
    {
        if ($this->cache_active) {
            $cache_key = md5($sql);
            if (!array_key_exists($cache_key, static::$metadata_cache)) {
                $md = $this->readColumnsMetadata($sql);
                static::$metadata_cache[$cache_key] = $md;
            }

            return static::$metadata_cache[$cache_key];
        } else {
            return $this->readColumnsMetadata($sql);
        }
    }

    /**
     * Read metadata information from source.
     *
     * @throws Exception\UnsupportedTypeException
     * @throws Exception\AmbiguousColumnException
     *
     * @param string $sql
     *
     * @return ColumnsMetadata
     */
    abstract protected function readColumnsMetadata($sql);

    /**
     * Optimization, will add false condition to the query
     * so the metadata loading will be faster.
     *
     * @param string $sql query string
     *
     * @return string
     */
    protected function getEmptiedQuery($sql)
    {
        // see the reason why in Vision_Store_Adapter_ZendDbSelect::getMetatData
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
