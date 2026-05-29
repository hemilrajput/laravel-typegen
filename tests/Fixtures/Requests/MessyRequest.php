<?php

namespace Hemilrajput\TypeGen\Tests\Fixtures\Requests;

use Hemilrajput\TypeGen\Attributes\TypeScript;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[TypeScript]
class MessyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // 1. Depends on route (should hit try/catch or skip)
            'id' => ['required', 'exists:users,id', Rule::unique('users')->ignore($this->route('user'))],

            // 2. Custom Rule class (should fall through to unknown)
            'custom' => ['required', new class implements \Illuminate\Contracts\Validation\Rule
            {
                public function passes($attribute, $value)
                {
                    return true;
                }

                public function message()
                {
                    return '';
                }
            }],

            // 3. Rule::in() object
            'status' => ['required', Rule::in(['active', 'inactive'])],

            // 4. Deep nested arrays of objects
            'items' => ['required', 'array'],
            'items.*.name' => ['required', 'string'],
            'items.*.qty' => ['required', 'integer'],
            'items.*.metadata.key' => ['string'],
        ];
    }
}
