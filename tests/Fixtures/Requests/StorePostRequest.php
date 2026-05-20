<?php

namespace hemilrajput\TypeGen\Tests\Fixtures\Requests;

use hemilrajput\TypeGen\Attributes\TypeScript;
use Illuminate\Foundation\Http\FormRequest;

#[TypeScript]
class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'author.name' => ['required', 'string'],
            'author.age' => ['nullable', 'integer'],
        ];
    }
}
