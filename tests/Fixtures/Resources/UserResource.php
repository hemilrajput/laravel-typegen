<?php

namespace Hemilrajput\TypeGen\Tests\Fixtures\Resources;

use Hemilrajput\TypeGen\Attributes\TypeScript;
use Illuminate\Http\Resources\Json\JsonResource;

#[TypeScript]
class UserResource extends JsonResource {}
