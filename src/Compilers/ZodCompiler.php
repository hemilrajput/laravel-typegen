<?php

namespace Hemilrajput\TypeGen\Compilers;

use Hemilrajput\TypeGen\Mappers\RuleToTypeMapper;

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
                $zodType = $this->toZodType($desc['type']);

                if ($desc['nullable']) {
                    $zodType .= '.nullable()';
                }
                if (! $desc['required']) {
                    $zodType .= '.optional()';
                }

                $lines[] = "{$pad}{$key}: {$zodType},";

                continue;
            }

            // Array of primitives (tags.* with __item_rules)
            if (isset($node['__item_rules']) && ! isset($node['__items'])) {
                $itemDesc = $this->mapper->map($node['__item_rules']);
                $zodItemType = $this->toZodType($itemDesc['type']);

                $zodType = "z.array({$zodItemType})";

                $parentDesc = isset($node['__rules']) ? $this->mapper->map($node['__rules']) : ['required' => false, 'nullable' => false];
                if ($parentDesc['nullable']) {
                    $zodType .= '.nullable()';
                }
                if (! $parentDesc['required']) {
                    $zodType .= '.optional()';
                }

                $lines[] = "{$pad}{$key}: {$zodType},";

                continue;
            }

            // Array of objects (tags.*.foo)
            if (isset($node['__items'])) {
                $inner = $this->compile($node['__items'], $indent + 2);
                $parentDesc = isset($node['__rules']) ? $this->mapper->map($node['__rules']) : ['required' => false, 'nullable' => false];

                $zodType = "z.array(z.object({\n{$inner}\n{$pad}}))";
                if ($parentDesc['nullable']) {
                    $zodType .= '.nullable()';
                }
                if (! $parentDesc['required']) {
                    $zodType .= '.optional()';
                }

                $lines[] = "{$pad}{$key}: {$zodType},";

                continue;
            }

            // Nested object (author.name, author.age)
            $rulesAtThisLevel = $node['__rules'] ?? null;
            $children = array_filter($node, fn ($k): bool => ! str_starts_with((string) $k, '__'), ARRAY_FILTER_USE_KEY);
            $inner = $this->compile($children, $indent + 2);

            $parentDesc = $rulesAtThisLevel ? $this->mapper->map($rulesAtThisLevel) : ['required' => false, 'nullable' => false];

            $zodType = "z.object({\n{$inner}\n{$pad}})";
            if ($parentDesc['nullable']) {
                $zodType .= '.nullable()';
            }
            if (! $parentDesc['required']) {
                $zodType .= '.optional()';
            }

            $lines[] = "{$pad}{$key}: {$zodType},";
        }

        return implode("\n", $lines);
    }

    protected function toZodType(string $type): string
    {
        if (str_ends_with($type, '[]')) {
            $base = substr($type, 0, -2);

            return 'z.array('.$this->toZodType($base).')';
        }

        return match ($type) {
            'string' => 'z.string()',
            'number' => 'z.number()',
            'boolean' => 'z.boolean()',
            'any', 'unknown' => 'z.any()',
            'File' => 'z.any()',
            default => $this->handleComplexType($type),
        };
    }

    protected function handleComplexType(string $type): string
    {
        if (str_contains($type, '|')) {
            $parts = array_map(trim(...), explode('|', $type));
            $literals = [];
            foreach ($parts as $part) {
                // If it's a string literal like 'admin'
                if (preg_match('/^[\'"](.*)[\'"]$/', $part)) {
                    $literals[] = "z.literal({$part})";
                } elseif (is_numeric($part)) {
                    $literals[] = "z.literal({$part})";
                } else {
                    return 'z.any()'; // Fallback if mixed
                }
            }
            if (count($literals) === 1) {
                return $literals[0];
            }
            $joined = implode(', ', $literals);

            return "z.union([{$joined}])";
        }

        // If it's a PascalCase word, it's likely an Enum reference
        if (preg_match('/^[A-Z]\w*$/', $type)) {
            return "z.nativeEnum({$type})";
        }

        return 'z.any()';
    }
}
