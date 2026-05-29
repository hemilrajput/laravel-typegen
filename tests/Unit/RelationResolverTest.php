<?php

use Hemilrajput\TypeGen\Relations\RelationDetector;
use Hemilrajput\TypeGen\Relations\RelationResolver;
use Hemilrajput\TypeGen\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

uses(TestCase::class);

class TestPostForResolver extends Model {}

class TestUserForResolver extends Model
{
    public function posts(): HasMany
    {
        return $this->hasMany(TestPostForResolver::class);
    }
}

class TestCommentForResolver extends Model
{
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}

class BrokenModel extends Model
{
    public function broken(): HasMany
    {
        throw new Exception('Broken');
    }
}

it('resolves the related model class for a HasMany', function () {
    $resolved = (new RelationResolver(new RelationDetector))
        ->resolve(TestUserForResolver::class, 'posts');

    expect($resolved['kind'])->toBe('collection');
    expect($resolved['related'])->toBe(TestPostForResolver::class);
    expect($resolved['error'])->toBeNull();
});

it('returns morph types when morph map is registered', function () {
    Relation::enforceMorphMap([
        'post' => TestPostForResolver::class,
    ]);

    $resolved = (new RelationResolver(new RelationDetector))
        ->resolve(TestCommentForResolver::class, 'commentable');

    expect($resolved['kind'])->toBe('morph_to');
    expect($resolved['morph_types'])->toContain(TestPostForResolver::class);
});

it('returns an error without crashing when relation fails to instantiate', function () {
    $resolved = (new RelationResolver(new RelationDetector))
        ->resolve(BrokenModel::class, 'broken');

    expect($resolved['error'])->not->toBeNull();
});

class TestModelWithoutReturnType extends Model
{
    public function things()
    {
        return $this->hasMany(TestPostForResolver::class);
    }
}

it('resolves relations even when php return type is missing', function () {
    $resolved = (new RelationResolver(new RelationDetector))
        ->resolve(TestModelWithoutReturnType::class, 'things');

    expect($resolved['kind'])->toBe('collection');
    expect($resolved['related'])->toBe(TestPostForResolver::class);
    expect($resolved['error'])->toBeNull();
});
