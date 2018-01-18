<?php

declare(strict_types=1);

namespace Soluble\Metadata;

use ArrayObject;
use Soluble\Datatype\Column\Definition\AbstractColumnDefinition;

class ColumnsMetadata extends ArrayObject
{
    /**
     * Return specific column metadata.
     *
     * @throws Exception\UnexistentColumnException
     */
    public function getColumn(string $name): AbstractColumnDefinition
    {
        if (!$this->offsetExists($name)) {
            throw new Exception\UnexistentColumnException("Column '$name' does not exists in metadata");
        }

        return $this->offsetGet($name);
    }
}
