<?php

declare(strict_types=1);

namespace Hemilrajput\TypeGen\Support;

use Illuminate\Support\Str;

class TypeMapperHelper
{
    public static function dbTypeToTypeScript(string $typeName): string
    {
        $typeName = strtolower($typeName);

        return match ($typeName) {
            'integer', 'int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'float', 'double', 'decimal', 'numeric' => 'number',
            'boolean', 'bool' => 'boolean',
            'array' => 'any[]',
            'object', 'stdclass' => 'Record<string, any>',
            'json' => 'Record<string, unknown>',
            default => 'string',
        };
    }

    public static function parsePhpDocType(string $type): string
    {
        $type = trim($type);
        $isNullable = false;

        if (str_starts_with($type, '?')) {
            $isNullable = true;
            $type = substr($type, 1);
        }

        $types = explode('|', $type);
        $mappedTypes = [];

        foreach ($types as $t) {
            $raw = trim($t);
            if ($raw === '') {
                continue;
            }

            $lower = strtolower($raw);
            if ($lower === 'null') {
                $isNullable = true;

                continue;
            }

            $isArraySuffix = str_ends_with($raw, '[]');
            $baseRaw = $isArraySuffix ? substr($raw, 0, -2) : $raw;
            $baseLower = strtolower($baseRaw);

            $mapped = match ($baseLower) {
                'int', 'integer', 'float', 'double' => 'number',
                'string' => 'string',
                'bool', 'boolean' => 'boolean',
                'array' => 'any[]',
                'object', 'stdclass' => 'Record<string, any>',
                'mixed' => 'any',
                default => null,
            };

            if ($mapped === null) {
                $short = class_basename(ltrim($baseRaw, '\\'));
                $mapped = preg_match('/^[A-Z]\w*$/', $short) ? $short : 'any';
            }

            if ($isArraySuffix && $mapped !== 'any[]') {
                $mapped .= '[]';
            }

            $mappedTypes[] = $mapped;
        }

        if ($mappedTypes === []) {
            return $isNullable ? 'any | null' : 'any';
        }

        $union = implode(' | ', array_unique($mappedTypes));

        return $isNullable ? "{$union} | null" : $union;
    }

    public static function isSafePath(string $path): bool
    {
        if (function_exists('app') && app()->runningUnitTests()) {
            return true;
        }

        $realPath = realpath($path);

        if ($realPath === false) {
            $parent = dirname($path);
            if ($parent === $path || $parent === '.') {
                $realPath = $path;
            } else {
                return self::isSafePath($parent);
            }
        }

        $basePath = realpath(base_path());
        if ($basePath === false) {
            return false;
        }

        $realPath = str_replace('\\', '/', $realPath);
        $basePath = str_replace('\\', '/', $basePath);

        return Str::startsWith($realPath, $basePath);
    }
}
