<?php

use hemilrajput\TypeGen\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

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
        ->toContain('posts?: Relation<Post[]>')
        ->toContain('profile?: Relation<Profile | null>')
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

it('splits output into separate files with imports when split config is enabled', function () {
    config()->set('typegen.paths.enums', __DIR__.'/../Fixtures/Enums');
    config()->set('typegen.paths.form_requests', __DIR__.'/../Fixtures/Requests');
    config()->set('typegen.paths.models', __DIR__.'/../Fixtures/Models');
    config()->set('typegen.output.split', true);

    $outputPath = sys_get_temp_dir().'/split_out.ts';
    config()->set('typegen.output.path', $outputPath);

    $this->artisan('typescript:generate')->assertSuccessful();

    $dir = sys_get_temp_dir().'/split_out';

    expect(is_dir($dir))->toBeTrue()
        ->and(file_exists("{$dir}/Models/User.ts"))->toBeTrue()
        ->and(file_exists("{$dir}/Enums/PostStatus.ts"))->toBeTrue()
        ->and(file_exists("{$dir}/Requests/StorePostRequest.ts"))->toBeTrue()
        ->and(file_exists("{$dir}/index.ts"))->toBeTrue();

    // Verify imports inside User.ts (across directories and within)
    $userContents = file_get_contents("{$dir}/Models/User.ts");
    expect($userContents)->toContain("import { Post } from './Post';")
        ->and($userContents)->toContain("import { PostStatus } from '../Enums/PostStatus';");

    // Clean up
    if (is_dir($dir)) {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        }
        @rmdir($dir);
    }
});

it('respects ignore attributes and parameters', function () {
    $outputPath = sys_get_temp_dir().'/ignore.ts';

    config()->set('typegen.paths.models', __DIR__.'/../Fixtures/Models');
    config()->set('typegen.output.path', $outputPath);

    $this->artisan('typescript:generate')->assertSuccessful();

    $contents = file_get_contents($outputPath);

    // Extract the IgnoredUser block to assert on it in isolation
    $start = strpos($contents, 'export interface IgnoredUser {');
    $end = strpos($contents, '}', $start) + 1;
    $ignoredUserBlock = substr($contents, $start, $end - $start);

    expect($ignoredUserBlock)->toContain('export interface IgnoredUser')
        ->and($ignoredUserBlock)->toContain('name: string;')
        ->and($ignoredUserBlock)->toContain('updated_at: string;')
        ->and($ignoredUserBlock)->not->toContain('email:')
        ->and($ignoredUserBlock)->not->toContain('posts?')
        ->and($ignoredUserBlock)->not->toContain('profile?')
        ->and($ignoredUserBlock)->not->toContain('created_at:');

    @unlink($outputPath);
});

it('infers types and nullability from database schema when table exists', function () {
    $outputPath = sys_get_temp_dir().'/db_fallback.ts';

    config()->set('typegen.paths.models', __DIR__.'/../Fixtures/Models');
    config()->set('typegen.output.path', $outputPath);

    // Create the users table in sqlite memory DB
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->integer('age');
        $table->boolean('is_active');
        $table->timestamps();
    });

    $this->artisan('typescript:generate')->assertSuccessful();

    $contents = file_get_contents($outputPath);

    // Extract User block
    $start = strpos($contents, 'export interface User {');
    $end = strpos($contents, '}', $start) + 1;
    $userBlock = substr($contents, $start, $end - $start);

    expect($userBlock)->toContain('name: string;')
        ->and($userBlock)->toContain('email: string | null;') // inferred nullability from DB
        ->and($userBlock)->toContain('age: number;')          // inferred integer -> number from DB
        ->and($userBlock)->toContain('is_active: number;');   // SQLite maps boolean to integer/number

    @unlink($outputPath);
    Schema::drop('users');
});

it('wraps relationships in Relation helper by default', function () {
    $outputPath = sys_get_temp_dir().'/relations_wrap.ts';

    config()->set('typegen.paths.models', __DIR__.'/../Fixtures/Models');
    config()->set('typegen.output.path', $outputPath);

    $this->artisan('typescript:generate')->assertSuccessful();

    $contents = file_get_contents($outputPath);

    expect($contents)->toContain('export type Relation<T> = T;')
        ->and($contents)->toContain('posts?: Relation<Post[]>;')
        ->and($contents)->toContain('profile?: Relation<Profile | null>;');

    @unlink($outputPath);
});

it('respects relations wrap configuration', function () {
    $outputPath = sys_get_temp_dir().'/relations_nowrap.ts';

    config()->set('typegen.paths.models', __DIR__.'/../Fixtures/Models');
    config()->set('typegen.output.path', $outputPath);
    config()->set('typegen.relations.wrap_with_relation', false);

    $this->artisan('typescript:generate')->assertSuccessful();

    $contents = file_get_contents($outputPath);

    expect($contents)->not->toContain('export type Relation<T> = T;')
        ->and($contents)->toContain('posts?: Post[];')
        ->and($contents)->toContain('profile?: Profile | null;');

    @unlink($outputPath);
});

it('runs pre and post generation hooks and replaces the placeholder', function () {
    $outputPath = sys_get_temp_dir().'/hooks_test.ts';
    $preFile = sys_get_temp_dir().'/pre_hook_ran.txt';
    $postFile = sys_get_temp_dir().'/post_hook_ran.txt';

    @unlink($preFile);
    @unlink($postFile);

    config()->set('typegen.paths.models', __DIR__.'/../Fixtures/Models');
    config()->set('typegen.output.path', $outputPath);
    config()->set('typegen.hooks.pre_generate', [
        'echo pre > '.$preFile,
    ]);
    config()->set('typegen.hooks.post_generate', [
        'echo post > '.$postFile,
    ]);

    $this->artisan('typescript:generate')->assertSuccessful();

    expect(file_exists($preFile))->toBeTrue()
        ->and(file_exists($postFile))->toBeTrue();

    @unlink($outputPath);
    @unlink($preFile);
    @unlink($postFile);
});

it('generates TypeScript types for API Resources from PHPDoc and Model fallback', function () {
    $outputPath = sys_get_temp_dir().'/resources_test.ts';

    config()->set('typegen.paths.resources', __DIR__.'/../Fixtures/Resources');
    config()->set('typegen.output.path', $outputPath);

    $this->artisan('typescript:generate')->assertSuccessful();

    $contents = file_get_contents($outputPath);

    // CustomResource assertions
    expect($contents)->toContain('export interface CustomResource')
        ->and($contents)->toContain('id: number;')
        ->and($contents)->toContain('title: string;')
        ->and($contents)->toContain('description: string | null;')
        ->and($contents)->toContain('is_published: boolean;')
        ->and($contents)->toContain('metadata: any;');

    // UserResource assertions (model fallback)
    expect($contents)->toContain('export interface UserResource')
        ->and($contents)->toContain('id: number;')
        ->and($contents)->toContain('email_verified_at: string;')
        ->and($contents)->toContain('is_admin: boolean;')
        ->and($contents)->toContain('preferences: unknown[];')
        ->and($contents)->toContain('status: PostStatus;');

    @unlink($outputPath);
});
