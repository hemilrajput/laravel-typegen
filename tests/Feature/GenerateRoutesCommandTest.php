<?php

use Hemilrajput\TypeGen\Tests\TestCase;
use Illuminate\Support\Facades\Route;

uses(TestCase::class);

it('generates route types for named routes', function () {
    Route::get('/users', fn () => 'index')->name('users.index');
    Route::get('/users/{user}', fn () => 'show')->name('users.show');
    Route::get('/posts/{post}/{comment?}', fn () => 'show')->name('posts.comments.show');
    Route::get('/unnamed', fn () => 'unnamed'); // should be ignored

    $outputPath = sys_get_temp_dir().'/routes.ts';
    config()->set('typegen.output.routes_path', $outputPath);

    $this->artisan('typescript:routes')->assertSuccessful();

    $contents = file_get_contents($outputPath);

    expect($contents)
        ->toContain('export type RouteName =')
        ->toContain("'posts.comments.show'")
        ->toContain("'users.index'")
        ->toContain("'users.show'")
        ->not->toContain("'unnamed'")
        ->toContain("T extends 'users.index' ? {} :")
        ->toContain("T extends 'users.show' ? { user: string | number } :")
        ->toContain("T extends 'posts.comments.show' ? { post: string | number; comment?: string | number } :");

    @unlink($outputPath);
});

it('respects the --dry-run flag for routes', function () {
    Route::get('/users', fn () => 'index')->name('users.index');

    $outputPath = sys_get_temp_dir().'/routes_dry.ts';
    config()->set('typegen.output.routes_path', $outputPath);

    @unlink($outputPath);

    $this->artisan('typescript:routes', ['--dry-run' => true])
        ->assertSuccessful();

    expect(file_exists($outputPath))->toBeFalse();
});
