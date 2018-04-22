<?php

namespace Soluble\Metadata\Reader\Capability;

interface ReaderCapabilityInterface
{
    const BUILTIN_CAPABILITIES = [
        self::DETECT_AUTOINCREMENT,
        self::DETECT_CHAR_MAX_LENGTH,
        self::DETECT_COLUMN_DEFAULT,
        self::DETECT_NUMERIC_UNSIGNED,
        self::DETECT_PRIMARY_KEY,
        self::DETECT_GROUP_FUNCTION
    ];
    const DETECT_AUTOINCREMENT = 'detect_autoincrement';
    const DETECT_PRIMARY_KEY = 'detect_primary_key';
    const DETECT_COLUMN_DEFAULT = 'detect_column_default';
    const DETECT_CHAR_MAX_LENGTH = 'detect_char_max_length';
    const DETECT_NUMERIC_UNSIGNED = 'detect_numeric_unsigned';
    const DETECT_GROUP_FUNCTION = 'detect_group_function';

    public function addCapability(string $name): void;

    public function hasCapability(string $name): bool;

    /**
     * @return string[]
     */
    public function getCapabilities(): array;
}
