<?php

namespace Hemilrajput\TypeGen\Generators;

use Hemilrajput\TypeGen\Attributes\TypeScript;
use Hemilrajput\TypeGen\Mappers\RuleToTypeMapper;
use Hemilrajput\TypeGen\Mappers\RuleTree;
use Illuminate\Foundation\Http\FormRequest;
use ReflectionClass;

class FormRequestGenerator
{
    public function __construct(
        protected RuleToTypeMapper $mapper,
        protected RuleTree $tree,
        protected array $config,
    ) {}

    public function generate(string $requestClass): string
    {
        $reflectionClass = new ReflectionClass($requestClass);
        $name = $this->resolveName($reflectionClass);

        try {
            $rules = $this->extractRules($requestClass);
        } catch (\Throwable $e) {
            return "// SKIPPED: {$name} — rules() could not be invoked: {$e->getMessage()}";
        }

        if ($rules === []) {
            return "export interface {$name} {}";
        }

        $tree = $this->tree->build($rules);
        $body = $this->renderTree($tree, indent: 2);

        return "export interface {$name} {\n{$body}\n}";
    }

    protected function resolveName(ReflectionClass $reflectionClass): string
    {
        $attr = $reflectionClass->getAttributes(TypeScript::class)[0] ?? null;
        $override = $attr?->newInstance()->name;

        return $override ?? $reflectionClass->getShortName();
    }

    protected function extractRules(string $requestClass): array
    {
        /** @var FormRequest $instance */
        $instance = new $requestClass;

        if (! method_exists($instance, 'rules')) {
            return [];
        }

        return $instance->rules();
    }

    protected function renderTree(array $tree, int $indent): string
    {
        $lines = [];
        $pad = str_repeat(' ', $indent);

        foreach ($tree as $key => $node) {
            // Leaf with __rules
            if (isset($node['__rules']) && count($node) === 1) {
                $desc = $this->mapper->map($node['__rules']);
                $optional = $desc['required'] ? '' : '?';
                $type = $desc['nullable'] ? "{$desc['type']} | null" : $desc['type'];
                $lines[] = "{$pad}{$key}{$optional}: {$type};";

                continue;
            }

            // Array of primitives (tags.* with __item_rules)
            if (isset($node['__item_rules']) && ! isset($node['__items'])) {
                $itemDesc = $this->mapper->map($node['__item_rules']);
                $parentDesc = isset($node['__rules']) ? $this->mapper->map($node['__rules']) : ['required' => false, 'nullable' => false];
                $optional = $parentDesc['required'] ? '' : '?';
                $type = "{$itemDesc['type']}[]";
                if ($parentDesc['nullable']) {
                    $type .= ' | null';
                }
                $lines[] = "{$pad}{$key}{$optional}: {$type};";

                continue;
            }

            // Array of objects (tags.*.foo)
            if (isset($node['__items'])) {
                $inner = $this->renderTree($node['__items'], $indent + 2);
                $lines[] = "{$pad}{$key}: {\n{$inner}\n{$pad}}[];";

                continue;
            }

            // Nested object (author.name, author.age)
            $rulesAtThisLevel = $node['__rules'] ?? null;
            $children = array_filter($node, fn ($k): bool => ! str_starts_with((string) $k, '__'), ARRAY_FILTER_USE_KEY);
            $inner = $this->renderTree($children, $indent + 2);

            $optional = '';
            if ($rulesAtThisLevel) {
                $desc = $this->mapper->map($rulesAtThisLevel);
                $optional = $desc['required'] ? '' : '?';
            }

            $lines[] = "{$pad}{$key}{$optional}: {\n{$inner}\n{$pad}};";
        }

        return implode("\n", $lines);
    }
}
