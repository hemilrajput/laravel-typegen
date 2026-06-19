<?php

namespace Hemilrajput\TypeGen\Generators;

use Hemilrajput\TypeGen\Attributes\TypeScript;
use Hemilrajput\TypeGen\Attributes\TypeScriptIgnore;
use Hemilrajput\TypeGen\Mappers\CastTypeMapper;
use Hemilrajput\TypeGen\Relations\RelationResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
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
        $fields = $this->collectFields($instance, $ignore);
        $relationResult = $this->collectRelations($reflectionClass, $modelClass, $ignore);

        $allLines = [];
        foreach ($fields as $field => $type) {
            $allLines[] = "  {$field}: {$type};";
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
    protected function collectFields(Model $model, array $ignore = []): array
    {
        $fields = [];
        $table = $model->getTable();

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

            $fields[$attr] = ($dbColumn['nullable'] ?? false) ? "{$baseType} | null" : $baseType;
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

            $fields[$appended] = isset($casts[$appended]) ? $this->mapper->toTypeScript($casts[$appended]) : 'any';
        }

        return $fields;
    }

    protected function dbTypeToTypeScript(string $typeName): string
    {
        $typeName = strtolower($typeName);

        return match ($typeName) {
            'integer', 'int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'float', 'double', 'decimal', 'numeric' => 'number',
            'boolean', 'bool' => 'boolean',
            'json' => 'any',
            default => 'string',
        };
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
