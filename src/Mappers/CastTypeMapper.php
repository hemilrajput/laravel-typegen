<?php

namespace Hemilrajput\TypeGen\Mappers;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsStringable;

class CastTypeMapper
{
    /** @var array<string,string> */
    protected array $map;

    public function __construct(array $overrides = [])
    {
        $this->map = array_merge($this->defaults(), $overrides);
    }

    public function toTypeScript(string $cast): string
    {
        // strip parameter portion: "decimal:2" -> "decimal"
        $base = ltrim(explode(':', $cast)[0], '\\');

        if (isset($this->map[$base])) {
            return $this->map[$base];
        }

        // Also check if any key in map matches without backslash
        foreach ($this->map as $key => $type) {
            if (ltrim($key, '\\') === $base) {
                return $type;
            }
        }

        // class-string cast (enum or custom Cast class)
        if (class_exists($base) || enum_exists($base)) {
            if (enum_exists($base)) {
                return (new \ReflectionClass($base))->getShortName();
            }

            // Check if it's a native Laravel cast
            if (is_a($base, AsCollection::class, true)) {
                return 'any[]';
            }
            if (is_a($base, AsArrayObject::class, true)) {
                return 'Record<string, unknown>';
            }
            if (is_a($base, AsStringable::class, true)) {
                return 'string';
            }

            // Inspect custom CastsAttributes
            if (is_a($base, CastsAttributes::class, true)) {
                try {
                    $reflectionMethod = new \ReflectionMethod($base, 'get');
                    $returnType = $reflectionMethod->getReturnType();
                    if ($returnType instanceof \ReflectionNamedType) {
                        return $this->phpTypeToTypeScript($returnType->getName());
                    }
                } catch (\ReflectionException) {
                    // fall back
                }
            }

            return 'unknown';
        }

        return 'unknown';
    }

    protected function phpTypeToTypeScript(string $type): string
    {
        return match (strtolower($type)) {
            'int', 'integer', 'float', 'double' => 'number',
            'string' => 'string',
            'bool', 'boolean' => 'boolean',
            'array' => 'any[]',
            default => 'unknown',
        };
    }

    /** @return array<string,string> */
    protected function defaults(): array
    {
        return [
            // primitives
            'int' => 'number',
            'integer' => 'number',
            'real' => 'number',
            'float' => 'number',
            'double' => 'number',
            'decimal' => 'number',
            'string' => 'string',
            'bool' => 'boolean',
            'boolean' => 'boolean',
            'array' => 'unknown[]',
            'json' => 'Record<string, unknown>',
            'object' => 'Record<string, unknown>',
            'collection' => 'unknown[]',
            // dates → string (ISO) by default; teams can override
            'date' => 'string',
            'datetime' => 'string',
            'immutable_date' => 'string',
            'immutable_datetime' => 'string',
            'timestamp' => 'string',
            // misc
            'encrypted' => 'string',
            'hashed' => 'string',
        ];
    }

    public function register(string $cast, string $type): self
    {
        $this->map[ltrim($cast, '\\')] = $type;

        return $this;
    }
}
