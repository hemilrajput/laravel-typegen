<?php

use hemilrajput\TypeGen\Mappers\RuleTree;

it('builds a tree from dot notation', function () {
    $tree = (new RuleTree)->build([
        'title' => ['required', 'string'],
        'author.name' => ['required', 'string'],
        'author.age' => ['integer'],
        'tags' => ['array'],
        'tags.*' => ['string'],
    ]);

    expect($tree['title']['__rules'])->toBe(['required', 'string']);
    expect($tree['author']['name']['__rules'])->toBe(['required', 'string']);
    expect($tree['tags']['__item_rules'])->toBe(['string']);
});

it('handles nested arrays of objects', function () {
    $tree = (new RuleTree)->build([
        'posts' => ['required', 'array'],
        'posts.*.title' => ['required', 'string'],
        'posts.*.body' => ['string'],
    ]);

    expect($tree['posts']['__items']['title']['__rules'])->toBe(['required', 'string']);
    expect($tree['posts']['__items']['body']['__rules'])->toBe(['string']);
});
