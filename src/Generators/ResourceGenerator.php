<?php

namespace Hemilrajput\TypeGen\Generators;

use Hemilrajput\TypeGen\Attributes\TypeScript;
use Hemilrajput\TypeGen\Mappers\CastTypeMapper;
use Hemilrajput\TypeGen\Relations\RelationDetector;
use Hemilrajput\TypeGen\Relations\RelationResolver;
use Hemilrajput\TypeGen\Scanners\ResourceAstVisitor;
use Illuminate\Support\Facades\Schema;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use ReflectionClass;

class ResourceGenerator
{
    public function __construct(
        protected CastTypeMapper $mapper,
        protected array $config,
        protected ?RelationResolver $resolver = null,
    ) {
        $this->resolver ??= new RelationResolver(new RelationDetector);
    }

    public function generate(string $resourceClass): string
    {
        $reflectionClass = new ReflectionClass($resourceClass);
        $name = $reflectionClass->getShortName();
        $fields = $this->collectFields($resourceClass, $reflectionClass);

        $allLines = [];
        foreach ($fields as $field => $type) {
            $allLines[] = "  {$field}: {$type};";
        }

        $body = implode("\n", $allLines);
        $style = $this->config['output']['style'] ?? 'interface';
        $keyword = $style === 'type' ? "export type {$name} =" : "export interface {$name}";
        $opener = $style === 'type' ? ' {' : ' {';

        return "{$keyword}{$opener}\n{$body}\n}";
    }

    /** @return array<string,string> */
    protected function collectFields(string $resourceClass, ReflectionClass $reflectionClass): array
    {
        if (class_exists(ParserFactory::class)) {
            $astFields = $this->collectFieldsFromAst($resourceClass, $reflectionClass);
            if ($astFields !== []) {
                return $astFields;
            }
        }

        $fields = [];
        $docComment = $reflectionClass->getDocComment();

        if ($docComment) {
            preg_match_all('/@property(?:-read)?\s+([^\s]+)\s+\$(\w+)/', $docComment, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $type = $match[1];
                $name = $match[2];
                $fields[$name] = $this->parsePhpDocType($type);
            }
        }

        // Fallback to matching model if no properties are defined via PHPDoc
        if ($fields === []) {
            $modelClass = $this->guessModelClass($reflectionClass);
            if ($modelClass) {
                $instance = new $modelClass;
                // Primary key
                $fields[$instance->getKeyName()] = $instance->getKeyType() === 'int' ? 'number' : 'string';
                // Casts
                foreach ($instance->getCasts() as $attr => $cast) {
                    $fields[$attr] = $this->mapper->toTypeScript($cast);
                }
                // Fillable
                foreach ($instance->getFillable() as $attr) {
                    $fields[$attr] ??= 'string';
                }
            }
        }

        return $fields;
    }

    protected function collectFieldsFromAst(string $resourceClass, ReflectionClass $reflectionClass): array
    {
        $fileName = $reflectionClass->getFileName();
        if (! $fileName || ! file_exists($fileName)) {
            return [];
        }

        if (! $reflectionClass->hasMethod('toArray')) {
            return [];
        }

        if ($reflectionClass->getMethod('toArray')->getDeclaringClass()->getName() !== $resourceClass) {
            return [];
        }

        try {
            $code = file_get_contents($fileName);
            $parser = (new ParserFactory)->createForNewestSupportedVersion();
            $ast = $parser->parse($code);

            $resourceAstVisitor = new ResourceAstVisitor;
            $nodeTraverser = new NodeTraverser;
            $nodeTraverser->addVisitor($resourceAstVisitor);
            $nodeTraverser->traverse($ast);

            if (! $resourceAstVisitor->toArrayReturn instanceof Array_) {
                return [];
            }

            return $this->parseArrayNode($resourceAstVisitor->toArrayReturn, $reflectionClass);
        } catch (\Throwable) {
            return [];
        }
    }

    protected function parseArrayNode(Array_ $array, ReflectionClass $reflectionClass): array
    {
        $fields = [];
        foreach ($array->items as $item) {
            /** @phpstan-ignore-next-line */
            if (! $item) {
                continue;
            }

            if ($item->key === null) {
                // Support $this->merge([...]) and $this->mergeWhen(..., [...])
                $expr = $item->value;
                if ($expr instanceof MethodCall && $expr->var instanceof Variable && $expr->var->name === 'this' && $expr->name instanceof Identifier) {
                    $methodName = $expr->name->toString();
                    if ($methodName === 'merge' && isset($expr->args[0]) && $expr->args[0]->value instanceof Array_) {
                        $mergedFields = $this->parseArrayNode($expr->args[0]->value, $reflectionClass);
                        $fields = array_merge($fields, $mergedFields);
                    } elseif ($methodName === 'mergeWhen' && isset($expr->args[1]) && $expr->args[1]->value instanceof Array_) {
                        $mergedFields = $this->parseArrayNode($expr->args[1]->value, $reflectionClass);
                        foreach ($mergedFields as $key => $type) {
                            $cleanKey = rtrim((string) $key, '?');
                            $fields[$cleanKey.'?'] = $type;
                        }
                    }
                }

                continue;
            }

            if (! $item->key instanceof String_) {
                continue;
            }
            $key = $item->key->value;

            if ($this->isOptional($item->value)) {
                $key .= '?';
            }

            $fields[$key] = $this->inferTypeFromExpr($item->value, $reflectionClass);
        }

        return $fields;
    }

    protected function isOptional(Expr $expr): bool
    {
        if ($expr instanceof MethodCall && $expr->var instanceof Variable && $expr->var->name === 'this' && ($expr->name instanceof Identifier && in_array($expr->name->toString(), ['when', 'whenLoaded', 'mergeWhen']))) {
            return true;
        }
        if ($expr instanceof StaticCall) {
            foreach ($expr->args as $arg) {
                if ($this->isOptional($arg->value)) {
                    return true;
                }
            }
        }
        if ($expr instanceof New_) {
            foreach ($expr->args as $arg) {
                if ($this->isOptional($arg->value)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function inferTypeFromExpr(Expr $expr, ReflectionClass $reflectionClass): string
    {
        if ($expr instanceof StaticCall && $expr->class instanceof Name && $expr->name instanceof Identifier && $expr->name->toString() === 'collection') {
            return $expr->class->getLast().'[]';
        }

        if ($expr instanceof New_ && $expr->class instanceof Name) {
            return $expr->class->getLast();
        }

        if ($expr instanceof PropertyFetch && $expr->var instanceof Variable && $expr->var->name === 'this' && $expr->name instanceof Identifier) {
            return $this->inferPropertyTypeFromModel($expr->name->toString(), $reflectionClass);
        }

        if ($expr instanceof MethodCall && $expr->var instanceof Variable && $expr->var->name === 'this' && $expr->name instanceof Identifier) {
            $methodName = $expr->name->toString();
            if ($methodName === 'when' && isset($expr->args[1])) {
                return $this->inferTypeFromExpr($expr->args[1]->value, $reflectionClass);
            }
            if ($methodName === 'whenLoaded') {
                if (isset($expr->args[1])) {
                    $secondArg = $expr->args[1]->value;
                    if ($secondArg instanceof ArrowFunction) {
                        return $this->inferTypeFromExpr($secondArg->expr, $reflectionClass);
                    }
                    if ($secondArg instanceof Closure) {
                        foreach ($secondArg->stmts as $stmt) {
                            if ($stmt instanceof Return_ && $stmt->expr instanceof Expr) {
                                return $this->inferTypeFromExpr($stmt->expr, $reflectionClass);
                            }
                        }
                    }

                    return $this->inferTypeFromExpr($secondArg, $reflectionClass);
                }

                if (isset($expr->args[0]) && $expr->args[0]->value instanceof String_) {
                    $relationName = $expr->args[0]->value->value;
                    $modelClass = $this->guessModelClass($reflectionClass);
                    if ($modelClass && class_exists($modelClass)) {
                        $resolved = $this->resolver->resolve($modelClass, $relationName);
                        if (! $resolved['error'] && $resolved['related']) {
                            $relatedShort = class_basename($resolved['related']);
                            $resourceName = $this->guessResourceName($resolved['related'], $reflectionClass);
                            $targetType = $resourceName ?? $relatedShort;

                            return $resolved['kind'] === 'collection' ? "{$targetType}[]" : "{$targetType} | null";
                        }
                    }
                }

                return 'any';
            }
        }

        if ($expr instanceof String_) {
            return $expr->value === 'any' ? 'any' : 'string';
        }

        if ($expr instanceof LNumber || $expr instanceof DNumber) {
            return 'number';
        }

        if ($expr instanceof ConstFetch) {
            $val = strtolower($expr->name->toString());

            return in_array($val, ['true', 'false'], true) ? 'boolean' : ($val === 'null' ? 'null' : 'any');
        }

        return 'any';
    }

    protected function inferPropertyTypeFromModel(string $propName, ReflectionClass $reflectionClass): string
    {
        $modelClass = $this->guessModelClass($reflectionClass);
        if ($modelClass) {
            try {
                $instance = new $modelClass;

                $casts = $instance->getCasts();
                if (isset($casts[$propName])) {
                    return $this->mapper->toTypeScript($casts[$propName]);
                }

                if ($instance->getKeyName() === $propName) {
                    return $instance->getKeyType() === 'int' ? 'number' : 'string';
                }

                if (in_array($propName, $instance->getDates())) {
                    return 'string';
                }

                if (in_array($propName, $instance->getFillable())) {
                    return 'string';
                }

                $table = $instance->getTable();
                if (Schema::hasTable($table)) {
                    foreach (Schema::getColumns($table) as $col) {
                        if ($col['name'] === $propName) {
                            $base = $this->dbTypeToTypeScript($col['type_name']);

                            return $col['nullable'] ? "{$base} | null" : $base;
                        }
                    }
                }

                return 'any';
            } catch (\Throwable) {
                // ignore
            }
        }

        return 'any';
    }

    protected function guessResourceName(string $modelClass, ReflectionClass $reflectionClass): ?string
    {
        $short = class_basename($modelClass);
        $candidate = $short.'Resource';
        $currentNs = $reflectionClass->getNamespaceName();

        if (class_exists($currentNs.'\\'.$candidate)) {
            return $candidate;
        }

        $appNamespace = 'App\\';
        if (function_exists('app')) {
            try {
                $appNamespace = app()->getNamespace();
            } catch (\Throwable) {
            }
        }

        if (class_exists($appNamespace."Http\\Resources\\{$candidate}")) {
            return $candidate;
        }

        return null;
    }

    protected function dbTypeToTypeScript(string $typeName): string
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

    protected function guessModelClass(ReflectionClass $reflectionClass): ?string
    {
        $attr = $reflectionClass->getAttributes(TypeScript::class)[0] ?? null;
        if ($attr) {
            $configuredModel = $attr->newInstance()->model;
            if ($configuredModel && class_exists($configuredModel)) {
                return $configuredModel;
            }
        }

        $docComment = $reflectionClass->getDocComment();
        if ($docComment && preg_match('/@mixin\s+([^\s]+)/', $docComment, $match)) {
            $mixinClass = ltrim($match[1], '\\');
            if (class_exists($mixinClass)) {
                return $mixinClass;
            }
        }

        $baseName = $reflectionClass->getShortName();
        if (! str_ends_with($baseName, 'Resource')) {
            return null;
        }

        $modelName = substr($baseName, 0, -8);
        $appNamespace = 'App\\';

        if (function_exists('app')) {
            try {
                $appNamespace = app()->getNamespace();
            } catch (\Throwable) {
            }
        }

        $possibleClasses = [
            $appNamespace."Models\\{$modelName}",
            $appNamespace.$modelName,
            "App\\Models\\{$modelName}",
            "App\\{$modelName}",
            "Hemilrajput\\TypeGen\\Tests\\Fixtures\\Models\\{$modelName}",
        ];

        foreach ($possibleClasses as $possibleClass) {
            if (class_exists($possibleClass)) {
                return $possibleClass;
            }
        }

        return null;
    }

    protected function parsePhpDocType(string $type): string
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
            $t = strtolower(trim($t));
            if ($t === 'null') {
                $isNullable = true;

                continue;
            }

            $mapped = match ($t) {
                'int', 'integer', 'float', 'double' => 'number',
                'string' => 'string',
                'bool', 'boolean' => 'boolean',
                'array' => 'any[]',
                'mixed' => 'any',
                default => 'any',
            };

            if ($mapped === 'any' && preg_match('/^[A-Z]\w+$/', trim($t))) {
                $mapped = trim($t);
            }
            $mappedTypes[] = $mapped;
        }

        $union = implode(' | ', array_unique($mappedTypes));
        if ($isNullable) {
            return "{$union} | null";
        }

        return $union;
    }
}
