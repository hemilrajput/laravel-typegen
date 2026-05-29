<?php

use Hemilrajput\TypeGen\Mappers\RuleToTypeMapper;
use Illuminate\Validation\Rules\In;

beforeEach(fn () => $this->mapper = new RuleToTypeMapper);

it('maps required string', function () {
    expect($this->mapper->map(['required', 'string']))
        ->toMatchArray(['type' => 'string', 'required' => true, 'nullable' => false]);
});

it('maps nullable integer', function () {
    expect($this->mapper->map(['nullable', 'integer']))
        ->toMatchArray(['type' => 'number', 'required' => false, 'nullable' => true]);
});

it('maps in: rule to union', function () {
    expect($this->mapper->map(['required', 'in:draft,published,archived']))
        ->toMatchArray(['type' => "'draft' | 'published' | 'archived'", 'required' => true]);
});

it('maps array rule', function () {
    expect($this->mapper->map(['required', 'array']))
        ->toMatchArray(['type' => 'unknown[]', 'required' => true]);
});

it('handles pipe-string rules', function () {
    expect($this->mapper->map('required|string|max:120'))
        ->toMatchArray(['type' => 'string', 'required' => true]);
});

it('handles array of rules with objects', function () {
    $rule = new In(['a', 'b']);
    expect($this->mapper->map(['required', $rule]))
        ->toMatchArray(['type' => "'a' | 'b'", 'required' => true]);
});
