<?php

use Hemilrajput\TypeGen\Compilers\ZodCompiler;
use Hemilrajput\TypeGen\Mappers\RuleToTypeMapper;
use Hemilrajput\TypeGen\Mappers\RuleTree;
use Illuminate\Validation\Rules\Enum;

it('compiles flat rules into a Zod schema', function (): void {
    $tree = (new RuleTree)->build([
        'title' => ['required', 'string'],
        'age' => ['integer', 'nullable'],
        'is_active' => ['boolean'],
    ]);

    $compiler = new ZodCompiler(new RuleToTypeMapper);
    $zod = $compiler->compile($tree, 2);

    expect($zod)->toContain('title: z.string(),');
    expect($zod)->toContain('age: z.number().nullish(),');
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

it('compiles in rules to z.enum()', function (): void {
    $tree = (new RuleTree)->build([
        'status' => ['required', 'in:draft,published'],
    ]);

    $compiler = new ZodCompiler(new RuleToTypeMapper);
    $zod = $compiler->compile($tree, 2);

    expect($zod)->toContain("status: z.enum(['draft', 'published']),");
});

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

it('compiles Enum references to z.enum()', function (): void {
    $tree = (new RuleTree)->build([
        'status' => ['required', new Enum(PostStatus::class)],
    ]);

    $compiler = new ZodCompiler(new RuleToTypeMapper);
    $zod = $compiler->compile($tree, 2);

    expect($zod)->toContain("status: z.enum(['draft', 'published']),");
});

it('compiles nullable unions with z.literal(null)', function (): void {
    $tree = (new RuleTree)->build([
        'status' => ['required', 'in:draft,published,null'],
    ]);

    $compiler = new ZodCompiler(new RuleToTypeMapper);
    $zod = $compiler->compile($tree, 2);

    // Because it contains 'null', it falls back to z.union
    expect($zod)->toContain("status: z.union([z.literal('draft'), z.literal('published'), z.literal(null)]),");
});

it('compiles sometimes to .optional() and nullable to .nullish()', function (): void {
    $tree = (new RuleTree)->build([
        'status' => ['sometimes', 'string'],
        'age' => ['sometimes', 'nullable', 'integer'],
    ]);

    $compiler = new ZodCompiler(new RuleToTypeMapper);
    $zod = $compiler->compile($tree, 2);

    expect($zod)->toContain('status: z.string().optional(),');
    expect($zod)->toContain('age: z.number().nullish(),');
});
