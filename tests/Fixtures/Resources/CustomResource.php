<?php

namespace Hemilrajput\TypeGen\Tests\Fixtures\Resources;

use Hemilrajput\TypeGen\Attributes\TypeScript;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property bool $is_published
 * @property mixed $metadata
 */
#[TypeScript]
class CustomResource extends JsonResource {}
