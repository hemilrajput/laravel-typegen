<?php

namespace Hemilrajput\TypeGen\Generators;

use Hemilrajput\TypeGen\Mappers\CastTypeMapper;
use Hemilrajput\TypeGen\Scanners\ResourceAstVisitor;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use ReflectionClass;

class ResourceGenerator
{
    public function __construct(
        protected CastTypeMapper $mapper,
        protected array $config,
    ) {}

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
        if (class_exists('\PhpParser\ParserFactory')) {
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
                    if (! isset($fields[$attr])) {
                        $fields[$attr] = 'string';
                    }
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

            $visitor = new ResourceAstVisitor;
            $traverser = new NodeTraverser;
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            if (! $visitor->toArrayReturn) {
                return [];
            }

            return $this->parseArrayNode($visitor->toArrayReturn, $reflectionClass);
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function parseArrayNode(Array_ $arrayNode, ReflectionClass $resourceReflection): array
    {
        $fields = [];
        foreach ($arrayNode->items as $item) {
            /** @phpstan-ignore-next-line */
            if (! $item || ! $item->key instanceof String_) {
                continue;
            }
            $key = $item->key->value;

            if ($this->isOptional($item->value)) {
                $key .= '?';
            }

            $fields[$key] = $this->inferTypeFromExpr($item->value, $resourceReflection);
        }

        return $fields;
    }

    protected function isOptional(Expr $expr): bool
    {
        if ($expr instanceof MethodCall && $expr->var instanceof Variable && $expr->var->name === 'this') {
            if ($expr->name instanceof Identifier && in_array($expr->name->toString(), ['when', 'whenLoaded', 'mergeWhen'])) {
                return true;
            }
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

    protected function inferTypeFromExpr(Expr $expr, ReflectionClass $resourceReflection): string
    {
        if ($expr instanceof StaticCall && $expr->class instanceof Name && $expr->name instanceof Identifier) {
            if ($expr->name->toString() === 'collection') {
                return $expr->class->getLast().'[]';
            }
        }

        if ($expr instanceof New_ && $expr->class instanceof Name) {
            return $expr->class->getLast();
        }

        if ($expr instanceof PropertyFetch && $expr->var instanceof Variable && $expr->var->name === 'this') {
            if ($expr->name instanceof Identifier) {
                return $this->inferPropertyTypeFromModel($expr->name->toString(), $resourceReflection);
            }
        }

        if ($expr instanceof MethodCall && $expr->var instanceof Variable && $expr->var->name === 'this') {
            if ($expr->name instanceof Identifier && $expr->name->toString() === 'whenLoaded') {
                // Try to guess relation type if possible? Default to any array for now
                return 'any';
            }
        }

        return 'any';
    }

    protected function inferPropertyTypeFromModel(string $propName, ReflectionClass $resourceReflection): string
    {
        $modelClass = $this->guessModelClass($resourceReflection);
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

                // If not casted, assume string for safety unless it's a known boolean/number from schema?
                // For now, fallback to any if we can't be sure, or string.
                return 'any';
            } catch (\Throwable) {
                // ignore
            }
        }

        return 'any';
    }

    protected function guessModelClass(ReflectionClass $reflectionClass): ?string
    {
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
