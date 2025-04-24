<?php

namespace Kirschbaum\Loop\Tools\Models\Concerns;

trait MapsDatabaseTypeToParameterType
{
    protected function mapDatabaseTypeToParameterType(string $dbType): string
    {
        $baseType = strtolower(preg_replace('/\(.*\)/', '', $dbType));

        if (in_array($baseType, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'float', 'double'])) {
            if ($baseType === 'tinyint' && strpos($dbType, '(1)') !== false) {
                return 'boolean';
            }

            return 'number';
        }

        if (in_array($baseType, ['date', 'datetime', 'timestamp', 'time', 'year'])) {
            return 'date';
        }

        if (in_array($baseType, ['bool', 'boolean'])) {
            return 'boolean';
        }

        if (in_array($baseType, ['json'])) {
            return 'json';
        }

        return 'string';
    }
}
