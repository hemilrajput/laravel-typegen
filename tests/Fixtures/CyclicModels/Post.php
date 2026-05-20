<?php

namespace hemilrajput\TypeGen\Tests\Fixtures\CyclicModels;

use hemilrajput\TypeGen\Attributes\TypeScript;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[TypeScript(includeRelations: ['user'])]
class Post extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
