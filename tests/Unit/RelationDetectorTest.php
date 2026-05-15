<?php

use Hemil09\TypeGen\Relations\RelationDetector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TestPost extends Model {}
class TestTeam extends Model {}

class TestUser extends Model
{
    public function posts(): HasMany
    {
        return $this->hasMany(TestPost::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(TestTeam::class);
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function untyped()
    {
        return null;
    }
}

it('detects collection relations', function () {
    $detected = (new RelationDetector)->detect(TestUser::class, 'posts');
    expect($detected['kind'])->toBe('collection');
});

it('detects single relations', function () {
    $detected = (new RelationDetector)->detect(TestUser::class, 'team');
    expect($detected['kind'])->toBe('single');
});

it('detects morph_to specially', function () {
    $detected = (new RelationDetector)->detect(TestUser::class, 'owner');
    expect($detected['kind'])->toBe('morph_to');
});

it('returns unknown for untyped methods', function () {
    $detected = (new RelationDetector)->detect(TestUser::class, 'untyped');
    expect($detected['kind'])->toBe('unknown');
});

it('returns unknown for missing methods', function () {
    $detected = (new RelationDetector)->detect(TestUser::class, 'nonexistent');
    expect($detected['kind'])->toBe('unknown');
});
