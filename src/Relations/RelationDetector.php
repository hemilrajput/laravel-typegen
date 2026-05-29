<?php

namespace hemilrajput\TypeGen\Relations;

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

        return $this->detectFromClass($returnType->getName());
    }

    /**
     * @return array{kind: 'collection'|'single'|'morph_to'|'unknown', return_type: ?string}
     */
    public function detectFromClass(string $className): array
    {
        // MorphTo gets special handling — related class is dynamic
        if ($className === MorphTo::class || is_subclass_of($className, MorphTo::class)) {
            return ['kind' => 'morph_to', 'return_type' => $className];
        }

        foreach (self::COLLECTION_RELATIONS as $relationClass) {
            if ($className === $relationClass || is_subclass_of($className, $relationClass)) {
                return ['kind' => 'collection', 'return_type' => $className];
            }
        }

        foreach (self::SINGLE_RELATIONS as $relationClass) {
            if ($className === $relationClass || is_subclass_of($className, $relationClass)) {
                return ['kind' => 'single', 'return_type' => $className];
            }
        }

        return ['kind' => 'unknown', 'return_type' => $className];
    }
}
