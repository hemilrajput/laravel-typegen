<?php

use Hemilrajput\TypeGen\Compilers\ZodCompiler;
use Hemilrajput\TypeGen\Mappers\RuleToTypeMapper;
use Hemilrajput\TypeGen\Mappers\RuleTree;

it('compiles flat rules into a Zod schema', function (): void {
    $tree = (new RuleTree)->build([
        'title' => ['required', 'string'],
        'age' => ['integer', 'nullable'],
        'is_active' => ['boolean'],
    ]);

    $compiler = new ZodCompiler(new RuleToTypeMapper);
    $zod = $compiler->compile($tree, 2);

    expect($zod)->toContain('title: z.string(),');
    expect($zod)->toContain('age: z.number().nullable().optional(),');
    expect($zod)->toContain('is_active: z.boolean().optional(),');
});

it('compiles nested object rules', function (): void {
    $tree = (new RuleTree)->build([
        'author' => ['required', 'array'],
        'author.name' => ['required', 'string'],
    ]);

    $compiler = new ZodCompiler(new RuleToTypeMapper);
    $zod = $compiler->compile($tree, 2);

    expect($zod)->toContain('author: z.object({');
    expect($zod)->toContain('name: z.string(),');
});

it('compiles array of primitives', function (): void {
    $tree = (new RuleTree)->build([
        'tags' => ['array'],
        'tags.*' => ['string'],
    ]);

    $compiler = new ZodCompiler(new RuleToTypeMapper);
    $zod = $compiler->compile($tree, 2);

    expect($zod)->toContain('tags: z.array(z.string()).optional(),');
});

it('compiles array of objects', function (): void {
    $tree = (new RuleTree)->build([
        'posts' => ['required', 'array'],
        'posts.*.title' => ['required', 'string'],
    ]);

    $compiler = new ZodCompiler(new RuleToTypeMapper);
    $zod = $compiler->compile($tree, 2);

    expect($zod)->toContain('posts: z.array(z.object({');
    expect($zod)->toContain('title: z.string(),');
});
