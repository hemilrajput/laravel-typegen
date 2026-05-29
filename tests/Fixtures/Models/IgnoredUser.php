<?php

namespace Hemilrajput\TypeGen\Tests\Fixtures\Models;

use Hemilrajput\TypeGen\Attributes\TypeScript;
use Hemilrajput\TypeGen\Attributes\TypeScriptIgnore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[TypeScript(includeRelations: ['posts', 'profile'], ignore: ['email', 'posts', 'created_at'])]
class IgnoredUser extends Model
{
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    #[TypeScriptIgnore]
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    protected $fillable = ['name', 'email'];
}
