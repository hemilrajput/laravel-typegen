<?php

namespace Hemil09\TypeGen\Tests\Fixtures\Models;

use Hemil09\TypeGen\Attributes\TypeScript;
use Hemil09\TypeGen\Tests\Fixtures\Enums\PostStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[TypeScript(includeRelations: ['posts', 'profile'])]
class User extends Model
{
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    protected $fillable = ['name', 'email', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
        'preferences' => 'array',
        'status' => PostStatus::class,
    ];
}
