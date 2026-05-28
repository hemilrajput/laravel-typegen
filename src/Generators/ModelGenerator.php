<?php

namespace hemilrajput\TypeGen\Generators;

use hemilrajput\TypeGen\Attributes\TypeScript;
use hemilrajput\TypeGen\Attributes\TypeScriptIgnore;
use hemilrajput\TypeGen\Mappers\CastTypeMapper;
use hemilrajput\TypeGen\Relations\RelationResolver;
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
        $reflection = new ReflectionClass($modelClass);

        $attr = $reflection->getAttributes(TypeScript::class)[0] ?? null;
        $ignore = $attr ? $attr->newInstance()->ignore : [];

        $name = $this->resolveName($reflection);
        $fields = $this->collectFields($instance, $ignore);
        $relationResult = $this->collectRelations($reflection, $modelClass, $ignore);

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

    protected function resolveName(ReflectionClass $reflection): string
    {
        $attr = $reflection->getAttributes(TypeScript::class)[0] ?? null;
        $override = $attr?->newInstance()->name;
        if ($override) {
            return $override;
        }

        $base = $reflection->getShortName();

        return ($this->config['naming']['model_prefix'] ?? '')
            .$base
            .($this->config['naming']['model_suffix'] ?? '');
    }

    /** @return array<string,string> */
    protected function collectFields(Model $instance, array $ignore = []): array
    {
        $fields = [];

        // primary key
        $keyName = $instance->getKeyName();
        if (! in_array($keyName, $ignore, true)) {
            $fields[$keyName] = $instance->getKeyType() === 'int' ? 'number' : 'string';
        }

        // casts
        foreach ($instance->getCasts() as $attr => $cast) {
            if (in_array($attr, $ignore, true)) {
                continue;
            }
            if (! $this->config['include_hidden'] && in_array($attr, $instance->getHidden(), true)) {
                continue;
            }
            $fields[$attr] = $this->mapper->toTypeScript($cast);
        }

        // Database columns inspection (fallback if schema table exists)
        $table = $instance->getTable();
        $dbColumns = [];
        try {
            if (Schema::hasTable($table)) {
                $dbColumns = Schema::getColumns($table);
            }
        } catch (\Throwable $e) {
            // Gracefully ignore DB exceptions if DB is not configured or table doesn't exist yet
        }

        if (! empty($dbColumns)) {
            foreach ($dbColumns as $column) {
                $attr = $column['name'];
                if (isset($fields[$attr]) || in_array($attr, $ignore, true)) {
                    continue;
                }
                if (! $this->config['include_hidden'] && in_array($attr, $instance->getHidden(), true)) {
                    continue;
                }

                $type = $this->dbTypeToTypeScript($column['type_name']);
                if ($column['nullable'] ?? false) {
                    $type = "{$type} | null";
                }
                $fields[$attr] = $type;
            }
        } else {
            // fillable fallback
            foreach ($instance->getFillable() as $attr) {
                if (isset($fields[$attr]) || in_array($attr, $ignore, true)) {
                    continue;
                }
                if (! $this->config['include_hidden'] && in_array($attr, $instance->getHidden(), true)) {
                    continue;
                }
                $fields[$attr] = 'string';
            }
        }

        // timestamps
        if ($this->config['include_timestamps'] && $instance->usesTimestamps()) {
            $createdAt = $instance->getCreatedAtColumn() ?? 'created_at';
            $updatedAt = $instance->getUpdatedAtColumn() ?? 'updated_at';

            if (! in_array($createdAt, $ignore, true) && ! isset($fields[$createdAt])) {
                $fields[$createdAt] = 'string';
            }
            if (! in_array($updatedAt, $ignore, true) && ! isset($fields[$updatedAt])) {
                $fields[$updatedAt] = 'string';
            }
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
    protected function collectRelations(ReflectionClass $reflection, string $modelClass, array $ignore = []): array
    {
        $attr = $reflection->getAttributes(TypeScript::class)[0] ?? null;
        $relations = $attr?->newInstance()->includeRelations ?? [];

        $fields = [];
        $discovered = [];

        foreach ($relations as $methodName) {
            if (in_array($methodName, $ignore, true)) {
                continue;
            }

            if ($reflection->hasMethod($methodName)) {
                $method = $reflection->getMethod($methodName);
                if ($method->getAttributes(TypeScriptIgnore::class)) {
                    continue;
                }
            }

            $resolved = $this->resolver->resolve($modelClass, $methodName);

            if ($resolved['error']) {
                // Log warning, emit unknown
                error_log("typegen: {$resolved['error']}");
                $fields[$methodName] = 'unknown';

                continue;
            }

            $type = $this->relationToType($resolved, $discovered);
            $fields[$methodName] = $type;
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
                    fn ($c) => class_basename($c),
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
