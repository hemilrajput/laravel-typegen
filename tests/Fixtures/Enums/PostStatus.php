<?php

namespace hemilrajput\TypeGen\Tests\Fixtures\Enums;

use hemilrajput\TypeGen\Attributes\TypeScript;

#[TypeScript]
enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
