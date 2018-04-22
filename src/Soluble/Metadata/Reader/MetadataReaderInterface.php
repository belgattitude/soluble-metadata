<?php

declare(strict_types=1);

namespace Soluble\Metadata\Reader;

use Soluble\Metadata\ColumnsMetadata;
use Soluble\Metadata\Reader\Capability\ReaderCapabilityInterface;

interface MetadataReaderInterface extends ReaderCapabilityInterface
{
    /**
     * Return columns metadata from query.
     */
    public function getColumnsMetadata(string $sql): ColumnsMetadata;

    /**
     * Return columns metadata from a table.
     */
    public function getTableMetadata(string $table): ColumnsMetadata;
}
