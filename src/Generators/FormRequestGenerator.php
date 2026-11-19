<?php

namespace Hemilrajput\TypeGen\Generators;

use Hemilrajput\TypeGen\Attributes\TypeScript;
use Hemilrajput\TypeGen\Compilers\ZodCompiler;
use Hemilrajput\TypeGen\Mappers\RuleToTypeMapper;
use Hemilrajput\TypeGen\Mappers\RuleTree;
use Hemilrajput\TypeGen\Scanners\FormRequestAstVisitor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use ReflectionClass;

class FormRequestGenerator
{
    public function __construct(
        protected RuleToTypeMapper $mapper,
        protected RuleTree $tree,
        protected array $config,
        protected ?ZodCompiler $zodCompiler = null,
    ) {
        $this->zodCompiler = $zodCompiler ?? new ZodCompiler($this->mapper);
    }

    public function generate(string $requestClass): string
    {
        $reflectionClass = new ReflectionClass($requestClass);
        $name = $this->resolveName($reflectionClass);

        try {
            $rules = $this->extractRules($requestClass, $reflectionClass);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Failed to extract rules from FormRequest [{$requestClass}]. If your rules() method depends on request state (e.g., \$this->route()), it cannot be statically analyzed. Error: ".$e->getMessage(), 0, $e);
        }

        if ($rules === []) {
            $zodSuffix = ($this->config['output']['zod'] ?? false)
                ? "\nexport const {$name}Schema = z.object({});\nexport type {$name}Payload = z.infer<typeof {$name}Schema>;"
                : '';

            return "export interface {$name} {}{$zodSuffix}";
        }

        $tree = $this->tree->build($rules);
        $body = $this->renderTree($tree, indent: 2);

        $output = "export interface {$name} {\n{$body}\n}";

        if ($this->config['output']['zod'] ?? false) {
            $zodBody = $this->zodCompiler->compile($tree, indent: 2);
            $output .= "\n\nexport const {$name}Schema = z.object({\n{$zodBody}\n});";
            $output .= "\nexport type {$name}Payload = z.infer<typeof {$name}Schema>;";
        }

        return $output;
    }

    protected function resolveName(ReflectionClass $reflectionClass): string
    {
        $attr = $reflectionClass->getAttributes(TypeScript::class)[0] ?? null;
        $override = $attr?->newInstance()->name;

        return $override ?? $reflectionClass->getShortName();
    }

    protected function extractRules(string $requestClass, ReflectionClass $reflectionClass): array
    {
        $instance = null;
        if (function_exists('app')) {
            try {
                $instance = app()->make($requestClass);
            } catch (\Throwable) {
                // Ignore and fall back to new
            }
        }
        $instance ??= new $requestClass;

        if ($instance instanceof FormRequest) {
            if (function_exists('app')) {
                $instance->setContainer(app());
            }

            // Set sandboxed route resolver
            $instance->setRouteResolver(function ($param = null): object {
                if ($param === null) {
                    return new class
                    {
                        public function parameter($key = null, $default = null)
                        {
                            return $default ?? '1';
                        }

                        public function __call(string $name, array $args)
                        {
                            return '1';
                        }

                        public function __get(string $name): mixed
                        {
                            return '1';
                        }
                    };
                }

                return new class
                {
                    public function __call(string $name, array $args)
                    {
                        return '1';
                    }

                    public function __get(string $name): mixed
                    {
                        return '1';
                    }

                    public function __toString(): string
                    {
                        return '1';
                    }
                };
            });

            // Set sandboxed user resolver
            $instance->setUserResolver(fn (): null => null);
        }

        if (! method_exists($instance, 'rules')) {
            return [];
        }

        try {
            $rules = $instance->rules();
        } catch (\Throwable $e) {
            $astRules = $this->extractRulesFromAst($requestClass, $reflectionClass);
            if ($astRules !== null) {
                $rules = $astRules;
            } else {
                throw $e;
            }
        }

        foreach ($rules as $field => $ruleList) {
            $normalized = is_string($ruleList) ? explode('|', $ruleList) : (array) $ruleList;
            if (in_array('confirmed', $normalized, true)) {
                $confirmField = $field.'_confirmation';
                if (! isset($rules[$confirmField])) {
                    $confirmRules = array_values(array_filter($normalized, fn ($r): bool => $r !== 'confirmed'));
                    $rules[$confirmField] = $confirmRules;
                }
            }
        }

        return $rules;
    }

    protected function extractRulesFromAst(string $requestClass, ReflectionClass $reflectionClass): ?array
    {
        $fileName = $reflectionClass->getFileName();
        if (! $fileName || ! file_exists($fileName)) {
            return null;
        }

        if (! $reflectionClass->hasMethod('rules')) {
            return [];
        }

        try {
            $code = file_get_contents($fileName);
            $parser = (new ParserFactory)->createForNewestSupportedVersion();
            $ast = $parser->parse($code);

            $visitor = new FormRequestAstVisitor;
            $traverser = new NodeTraverser;
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            if (! $visitor->rulesReturn instanceof Array_) {
                return null;
            }

            return $this->parseAstRulesArray($visitor->rulesReturn);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseAstRulesArray(Array_ $array): array
    {
        $rules = [];
        foreach ($array->items as $item) {
            if (! $item->key instanceof String_) {
                continue;
            }
            $field = $item->key->value;
            $rules[$field] = $this->parseAstRuleValue($item->value);
        }

        return $rules;
    }

    protected function parseAstRuleValue(Expr $expr): mixed
    {
        if ($expr instanceof String_) {
            return $expr->value;
        }

        if ($expr instanceof Array_) {
            $itemRules = [];
            foreach ($expr->items as $item) {
                if ($item->value instanceof String_) {
                    $itemRules[] = $item->value->value;
                } elseif ($item->value instanceof StaticCall) {
                    $method = $item->value->name instanceof Identifier ? $item->value->name->toString() : '';
                    if ($method === 'in' && isset($item->value->args[0]) && $item->value->args[0]->value instanceof Array_) {
                        $values = [];
                        foreach ($item->value->args[0]->value->items as $inItem) {
                            if ($inItem->value instanceof String_) {
                                $values[] = $inItem->value->value;
                            }
                        }
                        if ($values !== []) {
                            $itemRules[] = Rule::in($values);

                            continue;
                        }
                    }
                    $itemRules[] = 'string';
                } else {
                    $itemRules[] = 'string';
                }
            }

            return $itemRules;
        }

        return 'string';
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

                $parentDesc = isset($node['__rules']) ? $this->mapper->map($node['__rules']) : ['required' => false, 'nullable' => false];
                $optional = $parentDesc['required'] ? '' : '?';

                $type = "{\n{$inner}\n{$pad}}[]";
                if ($parentDesc['nullable']) {
                    $type .= ' | null';
                }

                $lines[] = "{$pad}{$key}{$optional}: {$type};";

                continue;
            }

            // Nested object (author.name, author.age)
            $rulesAtThisLevel = $node['__rules'] ?? null;
            $children = array_filter($node, fn ($k): bool => ! str_starts_with((string) $k, '__'), ARRAY_FILTER_USE_KEY);
            $inner = $this->renderTree($children, $indent + 2);

            $parentDesc = $rulesAtThisLevel ? $this->mapper->map($rulesAtThisLevel) : ['required' => false, 'nullable' => false];
            $optional = $parentDesc['required'] ? '' : '?';

            $type = "{\n{$inner}\n{$pad}}";
            if ($parentDesc['nullable']) {
                $type .= ' | null';
            }

            $lines[] = "{$pad}{$key}{$optional}: {$type};";
        }

        return implode("\n", $lines);
    }
}
