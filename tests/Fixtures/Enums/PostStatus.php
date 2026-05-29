<?php

namespace Hemilrajput\TypeGen\Tests\Fixtures\Enums;

use Hemilrajput\TypeGen\Attributes\TypeScript;

#[TypeScript]
enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
