<?php

namespace Hemilrajput\TypeGen\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = ['title', 'body'];
}
