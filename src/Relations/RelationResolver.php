<?php

namespace Hemil09\TypeGen\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Throwable;

class RelationResolver
{
    public function __construct(protected RelationDetector $detector) {}

    /**
     * @return array{
     *     kind: 'collection'|'single'|'morph_to'|'unknown',
     *     related: ?string,        // FQCN of related model, null if unresolvable
     *     morph_types: ?array,     // FQCN array for MorphTo when morphMap is registered
     *     error: ?string,
     * }
     */
    public function resolve(string $modelClass, string $methodName): array
    {
        $detected = $this->detector->detect($modelClass, $methodName);

        if ($detected['kind'] === 'unknown') {
            return [
                'kind' => 'unknown',
                'related' => null,
                'morph_types' => null,
                'error' => "Method {$modelClass}::{$methodName} did not return a recognized Eloquent relation type.",
            ];
        }

        try {
            /** @var Model $instance */
            $instance = new $modelClass;
            /** @var Relation $relation */
            $relation = $instance->{$methodName}();
        } catch (Throwable $e) {
            return [
                'kind' => $detected['kind'],
                'related' => null,
                'morph_types' => null,
                'error' => "Failed to instantiate relation {$modelClass}::{$methodName}: {$e->getMessage()}",
            ];
        }

        // MorphTo — related class is dynamic, read morph map
        if ($detected['kind'] === 'morph_to' && $relation instanceof MorphTo) {
            $morphMap = Relation::morphMap();
            $morphTypes = ! empty($morphMap) ? array_values($morphMap) : null;

            return [
                'kind' => 'morph_to',
                'related' => null,
                'morph_types' => $morphTypes,
                'error' => null,
            ];
        }

        // Regular relation — read related model class
        try {
            $related = $relation->getRelated()::class;
        } catch (Throwable $e) {
            return [
                'kind' => $detected['kind'],
                'related' => null,
                'morph_types' => null,
                'error' => "Could not resolve related model for {$modelClass}::{$methodName}: {$e->getMessage()}",
            ];
        }

        return [
            'kind' => $detected['kind'],
            'related' => $related,
            'morph_types' => null,
            'error' => null,
        ];
    }
}
