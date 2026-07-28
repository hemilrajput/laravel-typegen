<?php

namespace Hemilrajput\TypeGen\Tests\Fixtures\Resources;

use Hemilrajput\TypeGen\Attributes\TypeScript;
use Illuminate\Http\Resources\Json\JsonResource;

#[TypeScript]
class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->when(true, $this->title),
            $this->merge([
                'body' => $this->body,
            ]),
            $this->mergeWhen(true, [
                'slug' => 'any',
            ]),
        ];
    }
}
