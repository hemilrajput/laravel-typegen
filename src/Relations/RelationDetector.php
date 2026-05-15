<?php

namespace Hemil09\TypeGen\Relations;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use ReflectionMethod;

class RelationDetector
{
    /** Relation types that return collections (many records). */
    protected const COLLECTION_RELATIONS = [
        HasMany::class,
        HasManyThrough::class,
        BelongsToMany::class,
        MorphMany::class,
        MorphToMany::class,
    ];

    /** Relation types that return a single nullable record. */
    protected const SINGLE_RELATIONS = [
        HasOne::class,
        HasOneThrough::class,
        BelongsTo::class,
        MorphOne::class,
        MorphTo::class,
    ];

    /**
     * @return array{kind: 'collection'|'single'|'morph_to'|'unknown', return_type: ?string}
     */
    public function detect(string $modelClass, string $methodName): array
    {
        if (! method_exists($modelClass, $methodName)) {
            return ['kind' => 'unknown', 'return_type' => null];
        }

        $reflection = new ReflectionMethod($modelClass, $methodName);
        $returnType = $reflection->getReturnType();

        if (! $returnType instanceof \ReflectionNamedType) {
            return ['kind' => 'unknown', 'return_type' => null];
        }

        if ($returnType->isBuiltin()) {
            return ['kind' => 'unknown', 'return_type' => null];
        }

        $typeName = $returnType->getName();

        // MorphTo gets special handling — related class is dynamic
        if ($typeName === MorphTo::class || is_subclass_of($typeName, MorphTo::class)) {
            return ['kind' => 'morph_to', 'return_type' => $typeName];
        }

        foreach (self::COLLECTION_RELATIONS as $relationClass) {
            if ($typeName === $relationClass || is_subclass_of($typeName, $relationClass)) {
                return ['kind' => 'collection', 'return_type' => $typeName];
            }
        }

        foreach (self::SINGLE_RELATIONS as $relationClass) {
            if ($typeName === $relationClass || is_subclass_of($typeName, $relationClass)) {
                return ['kind' => 'single', 'return_type' => $typeName];
            }
        }

        return ['kind' => 'unknown', 'return_type' => $typeName];
    }
}
