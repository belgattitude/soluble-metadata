<?php

namespace Soluble\Metadata;

use ArrayObject;

class ColumnsMetadata extends ArrayObject
{

    /**
     * Return specific column metadata
     * 
     * @param string $name
     * @return \Soluble\Datatype\Column\Definition\AbstractColumnDefinition
     * @throws Exception\UnexistentColumnException
     */
    public function getColumn($name)
    {
        if (!$this->offsetExists($name)) {
            throw new Exception\UnexistentColumnException("Column '$name' does not exists in metadata");
        }
        return $this->offsetGet($name);
    }
}
