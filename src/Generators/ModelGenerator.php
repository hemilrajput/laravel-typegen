<?php

namespace Hemilrajput\TypeGen\Generators;

use Hemilrajput\TypeGen\Attributes\TypeScript;
use Hemilrajput\TypeGen\Attributes\TypeScriptIgnore;
use Hemilrajput\TypeGen\Mappers\CastTypeMapper;
use Hemilrajput\TypeGen\Relations\RelationResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;

class ModelGenerator
{
    public function __construct(
        protected CastTypeMapper $mapper,
        protected RelationResolver $resolver,
        protected array $config,
    ) {}

    public function generate(string $modelClass): array
    {
        /** @var Model $instance */
        $instance = new $modelClass;
        $reflectionClass = new ReflectionClass($modelClass);

        $attr = $reflectionClass->getAttributes(TypeScript::class)[0] ?? null;
        $ignore = $attr ? $attr->newInstance()->ignore : [];

        $name = $this->resolveName($reflectionClass);
        $fields = $this->collectFields($instance, $reflectionClass, $ignore);
        $relationResult = $this->collectRelations($reflectionClass, $modelClass, $ignore);

        $allLines = [];
        foreach ($fields as $field => $type) {
            if (str_starts_with($type, 'readonly ')) {
                $type = substr($type, 9);
                $allLines[] = "  readonly {$field}: {$type};";
            } else {
                $allLines[] = "  {$field}: {$type};";
            }
        }
        foreach ($relationResult['fields'] as $field => $type) {
            $allLines[] = "  {$field}?: {$type};";
        }

        $body = implode("\n", $allLines);

        $style = $this->config['output']['style'] ?? 'interface';
        $keyword = $style === 'type' ? "export type {$name} =" : "export interface {$name}";
        $opener = $style === 'type' ? ' {' : ' {';

        $block = "{$keyword}{$opener}\n{$body}\n}";

        return [
            'block' => $block,
            'discovered' => $relationResult['discovered'],
        ];
    }

    protected function resolveName(ReflectionClass $reflectionClass): string
    {
        $attr = $reflectionClass->getAttributes(TypeScript::class)[0] ?? null;
        $override = $attr?->newInstance()->name;
        if ($override) {
            return $override;
        }

        $base = $reflectionClass->getShortName();

        return ($this->config['naming']['model_prefix'] ?? '')
            .$base
            .($this->config['naming']['model_suffix'] ?? '');
    }

    /** @return array<string,string> */
    protected function collectFields(Model $model, ReflectionClass $reflectionClass, array $ignore = []): array
    {
        $fields = [];
        $table = $model->getTable();

        // 0. Parse PHPDoc @property annotations
        $docComment = $reflectionClass->getDocComment();
        if ($docComment) {
            preg_match_all('/@property(?:-read)?\s+([^\s]+)\s+\$(\w+)/', $docComment, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $type = $match[1];
                $name = $match[2];
                if (! in_array($name, $ignore, true)) {
                    $fields[$name] = $this->parsePhpDocType($type);
                }
            }
        }

        if (! Schema::hasTable($table)) {
            throw new \RuntimeException("Table [{$table}] does not exist. Please migrate your database before generating types.");
        }

        $dbColumns = Schema::getColumns($table);
        $casts = $model->getCasts();
        $hidden = $model->getHidden();
        $includeHidden = $this->config['include_hidden'] ?? false;

        // 1. Process Database Columns
        foreach ($dbColumns as $dbColumn) {
            $attr = $dbColumn['name'];

            if (in_array($attr, $ignore, true)) {
                continue;
            }
            if (! $includeHidden && in_array($attr, $hidden, true)) {
                continue;
            }

            if (isset($casts[$attr])) {
                $baseType = $this->mapper->toTypeScript($casts[$attr]);
            } else {
                $baseType = $this->dbTypeToTypeScript($dbColumn['type_name']);
            }

            $readonly = $model->isFillable($attr) ? '' : 'readonly ';

            $tsType = (($dbColumn['nullable'] ?? false) ? "{$baseType} | null" : $baseType);

            // If it's already defined via PHPDoc, don't overwrite the type but we can keep the readonly modifier logic.
            if (! isset($fields[$attr])) {
                $fields[$attr] = $readonly.$tsType;
            } elseif (! str_starts_with($fields[$attr], 'readonly ') && $readonly) {
                // Prepend readonly if not already present
                $fields[$attr] = $readonly.$fields[$attr];
            }
        }

        // 2. Process Appended Attributes
        foreach ($model->getAppends() as $appended) {
            if (in_array($appended, $ignore, true)) {
                continue;
            }
            if (isset($fields[$appended])) {
                continue;
            }
            if (! $includeHidden && in_array($appended, $hidden, true)) {
                continue;
            }

            if (isset($casts[$appended])) {
                $fields[$appended] = 'readonly '.$this->mapper->toTypeScript($casts[$appended]);

                continue;
            }

            $inferred = 'any';
            $studly = Str::studly($appended);
            $methodName = "get{$studly}Attribute";

            if (method_exists($model, $methodName)) {
                $rm = new \ReflectionMethod($model, $methodName);
                $rt = $rm->getReturnType();
                if ($rt instanceof \ReflectionNamedType) {
                    $inferred = $this->dbTypeToTypeScript($rt->getName());
                    if ($rt->allowsNull()) {
                        $inferred .= ' | null';
                    }
                }
            }

            $fields[$appended] = 'readonly '.$inferred;
        }

        return $fields;
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

    /**
     * @return array{fields: array<string,string>, discovered: array<string>}
     */
    protected function collectRelations(ReflectionClass $reflectionClass, string $modelClass, array $ignore = []): array
    {
        $attr = $reflectionClass->getAttributes(TypeScript::class)[0] ?? null;
        $relations = $attr?->newInstance()->includeRelations ?? [];

        $fields = [];
        $discovered = [];

        foreach ($relations as $relation) {
            if (in_array($relation, $ignore, true)) {
                continue;
            }

            if ($reflectionClass->hasMethod($relation)) {
                $method = $reflectionClass->getMethod($relation);
                if ($method->getAttributes(TypeScriptIgnore::class)) {
                    continue;
                }
            }

            $resolved = $this->resolver->resolve($modelClass, $relation);

            if ($resolved['error']) {
                // Log warning, emit unknown
                error_log("typegen: {$resolved['error']}");
                $fields[$relation] = 'unknown';

                continue;
            }

            $type = $this->relationToType($resolved, $discovered);
            $fields[$relation] = $type;
        }

        return ['fields' => $fields, 'discovered' => $discovered];
    }

    protected function relationToType(array $resolved, array &$discovered): string
    {
        $wrap = $this->config['relations']['wrap_with_relation'] ?? true;

        if ($resolved['kind'] === 'morph_to') {
            if ($resolved['morph_types']) {
                foreach ($resolved['morph_types'] as $morphClass) {
                    $discovered[] = $morphClass;
                }
                $union = implode(' | ', array_map(
                    class_basename(...),
                    $resolved['morph_types']
                ));

                $type = "({$union}) | null";

                return $wrap ? "Relation<{$type}>" : $type;
            }

            $type = 'unknown | null';

            return $wrap ? "Relation<{$type}>" : $type;
        }

        if (! $resolved['related']) {
            $type = 'unknown';

            return $wrap ? "Relation<{$type}>" : $type;
        }

        $discovered[] = $resolved['related'];
        $shortName = class_basename($resolved['related']);

        if ($resolved['kind'] === 'collection') {
            $type = "{$shortName}[]";

            return $wrap ? "Relation<{$type}>" : $type;
        }

        $type = "{$shortName} | null";

        return $wrap ? "Relation<{$type}>" : $type;
    }
}
