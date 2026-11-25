<?php

use Hemilrajput\TypeGen\Mappers\RuleToTypeMapper;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;

beforeEach(fn (): RuleToTypeMapper => $this->mapper = new RuleToTypeMapper);

it('maps required string', function (): void {
    expect($this->mapper->map(['required', 'string']))
        ->toMatchArray(['type' => 'string', 'required' => true, 'nullable' => false]);
});

it('maps nullable integer', function (): void {
    expect($this->mapper->map(['nullable', 'integer']))
        ->toMatchArray(['type' => 'number', 'required' => false, 'nullable' => true]);
});

it('handles required nullable fields with required key and nullable value', function (): void {
    expect($this->mapper->map(['required', 'nullable', 'string']))
        ->toMatchArray(['type' => 'string', 'required' => true, 'nullable' => true]);
});

it('maps in: rule to union', function (): void {
    expect($this->mapper->map(['required', 'in:draft,published,archived']))
        ->toMatchArray(['type' => "'draft' | 'published' | 'archived'", 'required' => true]);
});

it('maps array rule', function (): void {
    expect($this->mapper->map(['required', 'array']))
        ->toMatchArray(['type' => 'unknown[]', 'required' => true]);
});

it('handles pipe-string rules', function (): void {
    expect($this->mapper->map('required|string|max:120'))
        ->toMatchArray(['type' => 'string', 'required' => true]);
});

it('handles array of rules with objects', function (): void {
    $rule = new In(['a', 'b']);
    expect($this->mapper->map(['required', $rule]))
        ->toMatchArray(['type' => "'a' | 'b'", 'required' => true]);
});
it('extracts constraints from rules', function (): void {
    expect($this->mapper->map('required|string|min:8|max:255|email|regex:/^[a-z]+$/'))
        ->toMatchArray([
            'type' => 'string',
            'required' => true,
            'constraints' => [
                'min' => 8,
                'max' => 255,
                'email' => true,
                'regex' => '/^[a-z]+$/',
            ],
        ]);
});

enum SampleRuleStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

it('prioritizes in: and Enum rules over generic string/integer rules', function (): void {
    expect($this->mapper->map('required|string|in:light,dark,system'))
        ->toMatchArray(['type' => "'light' | 'dark' | 'system'", 'required' => true])
        ->and($this->mapper->map(['required', 'string', new Enum(SampleRuleStatus::class)]))
        ->toMatchArray(['type' => 'SampleRuleStatus', 'required' => true])
        ->and($this->mapper->map(['present', 'string']))
        ->toMatchArray(['type' => 'string', 'required' => true]);
});
