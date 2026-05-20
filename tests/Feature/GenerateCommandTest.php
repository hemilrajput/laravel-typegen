<?php

use hemilrajput\TypeGen\Tests\TestCase;

uses(TestCase::class);

it('generates a typescript file from a model with #[TypeScript]', function () {
    $outputPath = sys_get_temp_dir().'/test.ts';

    config()->set('typegen.paths.models', __DIR__.'/../Fixtures/Models');
    config()->set('typegen.output.path', $outputPath);

    $this->artisan('typescript:generate')->assertSuccessful();

    $contents = file_get_contents($outputPath);
    expect($contents)->toContain('export interface User');
    expect($contents)->toContain('is_admin: boolean');
    expect($contents)->toContain('name: string');

    @unlink($outputPath);
});

it('respects the --dry-run flag', function () {
    config()->set('typegen.paths.models', __DIR__.'/../Fixtures/Models');

    $this->artisan('typescript:generate', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('export interface User');
});

it('warns when no models are found', function () {
    config()->set('typegen.paths.models', __DIR__.'/NonExistent');

    $this->artisan('typescript:generate')
        ->assertSuccessful()
        ->expectsOutputToContain('No classes found');
});

it('generates types for an enum and a request together', function () {
    config()->set('typegen.paths.enums', __DIR__.'/../Fixtures/Enums');
    config()->set('typegen.paths.form_requests', __DIR__.'/../Fixtures/Requests');
    config()->set('typegen.paths.models', __DIR__.'/../Fixtures/Models');

    $outputPath = sys_get_temp_dir().'/v02.ts';
    config()->set('typegen.output.path', $outputPath);

    $this->artisan('typescript:generate')->assertSuccessful();

    $contents = file_get_contents($outputPath);

    expect($contents)
        ->toContain("export type PostStatus = 'draft' | 'published';")
        ->toContain('export interface StorePostRequest')
        ->toContain('title: string;')
        ->toContain('author: {')
        ->toContain('name: string;')
        ->toContain('export interface User');

    @unlink($outputPath);
});

it('handles messy form requests without crashing', function () {
    config()->set('typegen.paths.form_requests', __DIR__.'/../Fixtures/Requests');

    $outputPath = sys_get_temp_dir().'/messy.ts';
    config()->set('typegen.output.path', $outputPath);

    $this->artisan('typescript:generate')->assertSuccessful();

    $contents = file_get_contents($outputPath);

    // Verify messy request is there
    expect($contents)->toContain('export interface MessyRequest');

    // Verify object rules (Rule::in) are mapped
    expect($contents)->toContain("status: 'active' | 'inactive';");

    // Verify nested array of objects
    expect($contents)->toContain('items: {');
    expect($contents)->toContain('name: string;');
    expect($contents)->toContain('qty: number;');
    expect($contents)->toContain('metadata: {');
    expect($contents)->toContain('key?: string;');

    @unlink($outputPath);
});

it('auto-discovers related models and emits them together', function () {
    config()->set('typegen.paths.models', __DIR__.'/../Fixtures/Models');
    $outputPath = sys_get_temp_dir().'/v03.ts';
    config()->set('typegen.output.path', $outputPath);

    $this->artisan('typescript:generate')->assertSuccessful();

    $contents = file_get_contents($outputPath);

    expect($contents)
        ->toContain('export interface User')
        ->toContain('posts?: Post[]')
        ->toContain('profile?: Profile | null')
        ->toContain('export interface Post')   // auto-discovered
        ->toContain('export interface Profile'); // auto-discovered

    @unlink($outputPath);
});

it('handles cycles without infinite loop', function () {
    // User -> Post -> User
    config()->set('typegen.paths.models', __DIR__.'/../Fixtures/CyclicModels');
    $outputPath = sys_get_temp_dir().'/cycle.ts';
    config()->set('typegen.output.path', $outputPath);

    $this->artisan('typescript:generate')->assertSuccessful();

    $contents = file_get_contents($outputPath);
    expect($contents)->toContain('export interface User');
    expect($contents)->toContain('export interface Post');

    @unlink($outputPath);
});
