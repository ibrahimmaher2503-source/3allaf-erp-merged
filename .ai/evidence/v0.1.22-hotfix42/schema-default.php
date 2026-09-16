<?php

declare(strict_types=1);

return static function (object $metadata, ?string $expected, bool $isMariaDb): bool {
    foreach (['column_default', 'column_default_is_null'] as $property) {
        if (! property_exists($metadata, $property)) {
            throw new RuntimeException("Required column metadata {$property} is missing.");
        }
    }
    if (! in_array($metadata->column_default_is_null, [0, 1, '0', '1'], true)) {
        throw new RuntimeException('Column default SQL-null indicator is invalid.');
    }
    $isSqlNull = (int) $metadata->column_default_is_null === 1;
    if ($expected === null) {
        if ($isSqlNull) {
            return $metadata->column_default === null;
        }
        // MariaDB reports DEFAULT NULL as the unquoted metadata token NULL.
        // A SQL string literal has quotes in COLUMN_DEFAULT and must fail.
        return $isMariaDb && $metadata->column_default === 'NULL';
    }
    return ! $isSqlNull
        && (is_string($metadata->column_default) || is_int($metadata->column_default))
        && (string) $metadata->column_default === $expected;
};
