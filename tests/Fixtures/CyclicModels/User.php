<?php

namespace Hemilrajput\TypeGen\Tests\Fixtures\CyclicModels;

use Hemilrajput\TypeGen\Attributes\TypeScript;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[TypeScript(includeRelations: ['posts'])]
class User extends Model
{
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
