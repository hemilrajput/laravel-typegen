<?php

namespace Hemilrajput\TypeGen\Generators;

use Hemilrajput\TypeGen\Attributes\TypeScript;
use Hemilrajput\TypeGen\Attributes\TypeScriptIgnore;
use Hemilrajput\TypeGen\Mappers\CastTypeMapper;
use Hemilrajput\TypeGen\Relations\RelationResolver;
use Hemilrajput\TypeGen\Support\TypeMapperHelper;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

        return $this->formatModelName($reflectionClass->getShortName());
    }

    protected function formatModelName(string $base): string
    {
        $transform = strtolower((string) ($this->config['naming']['transform'] ?? 'pascal'));
        $transformed = match ($transform) {
            'snake' => Str::snake($base),
            'camel' => Str::camel($base),
            default => $base,
        };

        return ($this->config['naming']['model_prefix'] ?? '')
            .$transformed
            .($this->config['naming']['model_suffix'] ?? '');
    }

    /** @return array<string,string> */
    protected function collectFields(Model $model, ReflectionClass $reflectionClass, array $ignore = []): array
    {
        $fields = [];
        $table = $model->getTable();

        // Collect property-level #[TypeScriptIgnore]
        foreach ($reflectionClass->getProperties() as $property) {
            if ($property->getAttributes(TypeScriptIgnore::class) !== []) {
                $ignore[] = $property->getName();
            }
        }

        // 0. Parse PHPDoc @property annotations
        $docComment = $reflectionClass->getDocComment();
        if ($docComment) {
            preg_match_all('/@property(?:-read)?\s+([^\s]+)\s+\$(\w+)/', $docComment, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $type = $match[1];
                $name = $match[2];
                if (! in_array($name, $ignore, true)) {
                    $fields[$name] = TypeMapperHelper::parsePhpDocType($type);
                }
            }
        }

        $casts = $model->getCasts();
        $hidden = $model->getHidden();
        $includeHidden = $this->config['include_hidden'] ?? false;
        $includeTimestamps = $this->config['include_timestamps'] ?? true;
        $nullableByDefault = $this->config['nullable_by_default'] ?? false;
        $hasTable = Schema::hasTable($table);

        if (! $hasTable) {
            if ($this->config['strict_schema'] ?? true) {
                throw new \RuntimeException("Table [{$table}] does not exist. Please migrate your database before generating types.");
            }

            // Fallback mode without database table
            $keyName = $model->getKeyName();
            if ($keyName && ! in_array($keyName, $ignore, true) && ! isset($fields[$keyName])) {
                $keyType = $model->getKeyType() === 'int' ? 'number' : 'string';
                $fields[$keyName] = "readonly {$keyType}";
            }

            foreach ($model->getFillable() as $attr) {
                if (in_array($attr, $ignore, true)) {
                    continue;
                }
                if (! $includeHidden && in_array($attr, $hidden, true)) {
                    continue;
                }
                if (! isset($fields[$attr])) {
                    $baseType = isset($casts[$attr]) ? $this->mapper->toTypeScript($casts[$attr]) : 'string';
                    if ($nullableByDefault && ! str_contains($baseType, 'null')) {
                        $baseType .= ' | null';
                    }
                    $fields[$attr] = $baseType;
                }
            }

            foreach ($casts as $attr => $cast) {
                if (in_array($attr, $ignore, true)) {
                    continue;
                }
                if (! $includeHidden && in_array($attr, $hidden, true)) {
                    continue;
                }
                if (! isset($fields[$attr])) {
                    $readonly = $model->isFillable($attr) ? '' : 'readonly ';
                    $baseType = $this->mapper->toTypeScript($cast);
                    if ($nullableByDefault && $attr !== $keyName && ! str_contains($baseType, 'null')) {
                        $baseType .= ' | null';
                    }
                    $fields[$attr] = $readonly.$baseType;
                }
            }

            if ($includeTimestamps && $model->usesTimestamps()) {
                $created = $model->getCreatedAtColumn();
                $updated = $model->getUpdatedAtColumn();
                if ($created && ! in_array($created, $ignore, true) && ! isset($fields[$created])) {
                    $fields[$created] = 'readonly string | null';
                }
                if ($updated && ! in_array($updated, $ignore, true) && ! isset($fields[$updated])) {
                    $fields[$updated] = 'readonly string | null';
                }
            }
        } else {
            $dbColumns = Schema::getColumns($table);
            $timestampCols = $model->usesTimestamps()
                ? array_filter([$model->getCreatedAtColumn(), $model->getUpdatedAtColumn()])
                : [];

            // 1. Process Database Columns
            foreach ($dbColumns as $dbColumn) {
                $attr = $dbColumn['name'];

                if (in_array($attr, $ignore, true)) {
                    continue;
                }
                if (! $includeTimestamps && in_array($attr, $timestampCols, true)) {
                    continue;
                }
                if (! $includeHidden && in_array($attr, $hidden, true)) {
                    continue;
                }

                if (isset($casts[$attr])) {
                    $baseType = $this->mapper->toTypeScript($casts[$attr]);
                } else {
                    $baseType = TypeMapperHelper::dbTypeToTypeScript($dbColumn['type_name']);
                }

                $readonly = $model->isFillable($attr) ? '' : 'readonly ';

                $tsType = ($dbColumn['nullable'] ? "{$baseType} | null" : $baseType);

                // If it's already defined via PHPDoc, don't overwrite the type but we can keep the readonly modifier logic.
                if (! isset($fields[$attr])) {
                    $fields[$attr] = $readonly.$tsType;
                } elseif (! str_starts_with($fields[$attr], 'readonly ') && $readonly) {
                    // Prepend readonly if not already present
                    $fields[$attr] = $readonly.$fields[$attr];
                }
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

            $studly = Str::studly($appended);
            $modernMethodName = Str::camel($appended);
            $legacyMethodName = "get{$studly}Attribute";

            if (($reflectionClass->hasMethod($modernMethodName) && $reflectionClass->getMethod($modernMethodName)->getAttributes(TypeScriptIgnore::class) !== [])
                || ($reflectionClass->hasMethod($legacyMethodName) && $reflectionClass->getMethod($legacyMethodName)->getAttributes(TypeScriptIgnore::class) !== [])
            ) {
                continue;
            }

            if (isset($casts[$appended])) {
                $fields[$appended] = 'readonly '.$this->mapper->toTypeScript($casts[$appended]);

                continue;
            }

            $inferred = 'any';

            // Modern Accessors (Attribute::make)
            if (method_exists($model, $modernMethodName)) {
                $rm = new \ReflectionMethod($model, $modernMethodName);
                $rt = $rm->getReturnType();
                if ($rt instanceof \ReflectionNamedType && ltrim($rt->getName(), '\\') === Attribute::class) {
                    try {
                        $attribute = $rm->invoke($model);
                        if ($attribute->get) {
                            $closureReflection = new \ReflectionFunction($attribute->get);
                            $closureRt = $closureReflection->getReturnType();
                            if ($closureRt instanceof \ReflectionNamedType) {
                                $inferred = TypeMapperHelper::dbTypeToTypeScript($closureRt->getName());
                                if ($closureRt->allowsNull()) {
                                    $inferred .= ' | null';
                                }
                            }
                        }
                    } catch (\Throwable) {
                        // ignore errors invoking the accessor during static analysis
                    }
                }
            }

            // Legacy Accessors (getXAttribute)
            if ($inferred === 'any' && method_exists($model, $legacyMethodName)) {
                $rm = new \ReflectionMethod($model, $legacyMethodName);
                $rt = $rm->getReturnType();
                if ($rt instanceof \ReflectionNamedType) {
                    $inferred = TypeMapperHelper::dbTypeToTypeScript($rt->getName());
                    if ($rt->allowsNull()) {
                        $inferred .= ' | null';
                    }
                }
            }

            $fields[$appended] = 'readonly '.$inferred;
        }

        return $fields;
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
            $fieldName = $this->formatRelationName($relation);

            if ($resolved['error']) {
                // Log warning, emit unknown
                error_log("typegen: {$resolved['error']}");
                $fields[$fieldName] = 'unknown';

                continue;
            }

            $type = $this->relationToType($resolved, $discovered);
            $fields[$fieldName] = $type;
        }

        return ['fields' => $fields, 'discovered' => $discovered];
    }

    protected function formatRelationName(string $relation): string
    {
        $casing = $this->config['relations']['casing'] ?? 'camel';

        $normalized = strtolower(str_replace(['-', '_', ' '], '', (string) $casing));

        $name = match ($normalized) {
            'pascal', 'pascalcase', 'studly' => Str::studly($relation),
            'snake', 'snakecase' => Str::snake($relation),
            'kebab', 'kebabcase' => Str::kebab($relation),
            'raw', 'none' => $relation,
            default => Str::camel($relation),
        };

        if (preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*$/', $name)) {
            return $name;
        }

        $trimmed = trim($name, "'\"");

        return "'{$trimmed}'";
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
                    fn (string $morphClass): string => $this->formatModelName(class_basename($morphClass)),
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
        $shortName = $this->formatModelName(class_basename($resolved['related']));

        if ($resolved['kind'] === 'collection') {
            $type = "{$shortName}[]";

            return $wrap ? "Relation<{$type}>" : $type;
        }

        $type = "{$shortName} | null";

        return $wrap ? "Relation<{$type}>" : $type;
    }
}
