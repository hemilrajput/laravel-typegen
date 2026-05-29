<?php

namespace Hemilrajput\TypeGen\Mappers;

use Illuminate\Validation\Rules\Enum as EnumRule;
use Illuminate\Validation\Rules\In as InRule;

class RuleToTypeMapper
{
    /**
     * @param  array|string  $rules  raw rule list for a single field
     * @return array{type: string, required: bool, nullable: bool}
     */
    public function map(array|string $rules): array
    {
        $tokens = $this->normalize($rules);
        $type = null;
        $required = false;
        $nullable = false;
        $isArray = false;

        foreach ($tokens as $token) {
            // Object rules (Enum, In, Rule::in(...), etc.)
            if (is_object($token)) {
                $type ??= $this->describeObjectRule($token);

                continue;
            }

            if (! is_string($token)) {
                continue;
            }

            [$name, $arg] = $this->parseToken($token);

            match (true) {
                $name === 'required' => $required = true,
                $name === 'nullable' => $nullable = true,
                $name === 'sometimes' => $required = false,
                $name === 'array' => $isArray = true,
                in_array($name, ['string', 'email', 'url', 'uuid', 'ulid', 'ip', 'ipv4', 'ipv6', 'json', 'alpha', 'alpha_num', 'alpha_dash']) => $type ??= 'string',
                in_array($name, ['integer', 'numeric', 'decimal']) => $type ??= 'number',
                $name === 'boolean' => $type ??= 'boolean',
                in_array($name, ['date', 'date_format', 'before', 'after']) => $type ??= 'string',
                in_array($name, ['file', 'image', 'mimes']) => $type ??= 'File',
                $name === 'in' && $arg => $type ??= $this->inToUnion($arg),
                $name === 'enum' && $arg => $type ??= class_basename($arg),
                default => null,
            };
        }

        $type ??= 'unknown';
        if ($isArray) {
            $type = "{$type}[]";
        }

        return [
            'type' => $type,
            'required' => $required && ! $nullable,
            'nullable' => $nullable,
        ];
    }

    protected function normalize(array|string $rules): array
    {
        if (is_string($rules)) {
            return explode('|', $rules);
        }

        return $rules;
    }

    protected function parseToken(string $token): array
    {
        if (! str_contains($token, ':')) {
            return [$token, null];
        }
        [$name, $arg] = explode(':', $token, 2);

        return [$name, $arg];
    }

    protected function inToUnion(string $arg): string
    {
        $values = array_map(
            fn ($v) => is_numeric($v) ? $v : "'".trim($v, "\"' ")."'",
            explode(',', $arg),
        );

        return implode(' | ', $values);
    }

    protected function describeObjectRule(object $rule): ?string
    {
        // Laravel's Enum rule exposes the enum class.
        if ($rule instanceof EnumRule) {
            // The class is protected; reflect to read it.
            $ref = new \ReflectionClass($rule);
            if ($ref->hasProperty('type')) {
                $prop = $ref->getProperty('type');
                $prop->setAccessible(true);
                $enumClass = $prop->getValue($rule);
                if (is_string($enumClass) && enum_exists($enumClass)) {
                    return class_basename($enumClass);
                }
            }
        }

        if ($rule instanceof InRule) {
            $values = $rule->__toString(); // "in:a,b,c"

            return $this->inToUnion(substr($values, 3));
        }

        return null;
    }
}
