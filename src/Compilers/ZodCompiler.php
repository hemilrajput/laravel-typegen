<?php

namespace Hemilrajput\TypeGen\Compilers;

use Hemilrajput\TypeGen\Mappers\RuleToTypeMapper;
use Illuminate\Validation\Rules\Enum;

class ZodCompiler
{
    public function __construct(
        protected RuleToTypeMapper $mapper
    ) {}

    public function compile(array $tree, int $indent = 2): string
    {
        $lines = [];
        $pad = str_repeat(' ', $indent);

        foreach ($tree as $key => $node) {
            // Leaf with __rules
            if (isset($node['__rules']) && count($node) === 1) {
                $desc = $this->mapper->map($node['__rules']);
                $zodType = $this->toZodType($desc['type'], $desc);
                $zodType = $this->applyModifiers($zodType, $desc);

                $lines[] = "{$pad}{$key}: {$zodType},";

                continue;
            }

            // Array of primitives (tags.* with __item_rules)
            if (isset($node['__item_rules']) && ! isset($node['__items'])) {
                $itemDesc = $this->mapper->map($node['__item_rules']);
                $zodItemType = $this->toZodType($itemDesc['type'], $itemDesc);

                $zodType = "z.array({$zodItemType})";

                $parentDesc = isset($node['__rules']) ? $this->mapper->map($node['__rules']) : ['required' => false, 'nullable' => false];
                $zodType = $this->applyModifiers($zodType, $parentDesc);

                $lines[] = "{$pad}{$key}: {$zodType},";

                continue;
            }

            // Array of objects (tags.*.foo)
            if (isset($node['__items'])) {
                $inner = $this->compile($node['__items'], $indent + 2);
                $parentDesc = isset($node['__rules']) ? $this->mapper->map($node['__rules']) : ['required' => false, 'nullable' => false];

                $zodType = "z.array(z.object({\n{$inner}\n{$pad}}))";
                $zodType = $this->applyModifiers($zodType, $parentDesc);

                $lines[] = "{$pad}{$key}: {$zodType},";

                continue;
            }

            // Nested object (author.name, author.age)
            $rulesAtThisLevel = $node['__rules'] ?? null;
            $children = array_filter($node, fn ($k): bool => ! str_starts_with((string) $k, '__'), ARRAY_FILTER_USE_KEY);
            $inner = $this->compile($children, $indent + 2);

            $parentDesc = $rulesAtThisLevel ? $this->mapper->map($rulesAtThisLevel) : ['required' => false, 'nullable' => false];

            $zodType = "z.object({\n{$inner}\n{$pad}})";
            $zodType = $this->applyModifiers($zodType, $parentDesc);

            $lines[] = "{$pad}{$key}: {$zodType},";
        }

        return implode("\n", $lines);
    }

    protected function applyModifiers(string $zodType, array $desc): string
    {
        $nullable = $desc['nullable'] ?? false;
        $required = $desc['required'] ?? false;

        if ($nullable && ! $required) {
            return $zodType.'.nullish()';
        }

        if ($nullable) {
            $zodType .= '.nullable()';
        }
        if (! $required) {
            $zodType .= '.optional()';
        }

        return $zodType;
    }

    protected function toZodType(string $type, array $desc = []): string
    {
        if (str_ends_with($type, '[]')) {
            $base = substr($type, 0, -2);
            $baseDesc = $desc;
            $baseDesc['type'] = $base;

            return 'z.array('.$this->toZodType($base, $baseDesc).')';
        }

        return match ($type) {
            'string' => 'z.string()',
            'number' => 'z.number()',
            'boolean' => 'z.boolean()',
            'any', 'unknown' => 'z.any()',
            'File' => 'z.any()',
            default => $this->handleComplexType($type, $desc),
        };
    }

    protected function handleComplexType(string $type, array $desc = []): string
    {
        if (str_contains($type, '|')) {
            $parts = array_map(trim(...), explode('|', $type));
            $isAllStrings = true;
            $stringLiterals = [];
            $zLiterals = [];

            foreach ($parts as $part) {
                if (preg_match('/^[\'"](.*)[\'"]$/', $part)) {
                    $stringLiterals[] = $part;
                    $zLiterals[] = "z.literal({$part})";
                } elseif (is_numeric($part)) {
                    $isAllStrings = false;
                    $zLiterals[] = "z.literal({$part})";
                } elseif ($part === 'null') {
                    $isAllStrings = false;
                    $zLiterals[] = 'z.literal(null)';
                } else {
                    return 'z.any()'; // Fallback if mixed
                }
            }

            if ($isAllStrings && count($stringLiterals) > 1) {
                $joined = implode(', ', $stringLiterals);

                return "z.enum([{$joined}])";
            }

            if (count($zLiterals) === 1) {
                return $zLiterals[0];
            }

            $joined = implode(', ', $zLiterals);

            return "z.union([{$joined}])";
        }

        // If it's a PascalCase word, it's likely an Enum reference
        if (preg_match('/^[A-Z]\w*$/', $type)) {
            $enumValues = null;
            if (isset($desc['enum_class']) && enum_exists($desc['enum_class'])) {
                $enumValues = $this->extractEnumValues($desc['enum_class']);
            }

            if (is_array($enumValues) && count($enumValues) > 0) {
                $isAllStrings = true;
                foreach ($enumValues as $enumValue) {
                    if (! preg_match('/^[\'"](.*)[\'"]$/', (string) $enumValue)) {
                        $isAllStrings = false;
                        break;
                    }
                }

                if ($isAllStrings) {
                    $joined = implode(', ', $enumValues);

                    return "z.enum([{$joined}])";
                }
                $zLiterals = array_map(fn ($v): string => "z.literal({$v})", $enumValues);
                $joined = implode(', ', $zLiterals);

                return "z.union([{$joined}])";
            }

            return 'z.any()';
        }

        return 'z.any()';
    }

    protected function extractEnumValues(string $enumClass): array
    {
        $values = [];
        $reflectionEnum = new \ReflectionEnum($enumClass);
        foreach ($reflectionEnum->getCases() as $reflectionEnumUnitCase) {
            if ($reflectionEnumUnitCase instanceof \ReflectionEnumBackedCase) {
                $backingType = $reflectionEnum->getBackingType()?->getName();
                $val = $reflectionEnumUnitCase->getBackingValue();
                $values[] = $backingType === 'string'
                    ? "'".str_replace("'", "\\'", (string) $val)."'"
                    : (string) $val;
            } else {
                $values[] = "'".$reflectionEnumUnitCase->getName()."'";
            }
        }

        return $values;
    }
}
