<?php

declare(strict_types=1);

use Hemilrajput\TypeGen\Mappers\CastTypeMapper;

it('maps primitive casts correctly', function (): void {
    $mapper = new CastTypeMapper;

    expect($mapper->toTypeScript('int'))->toBe('number')
        ->and($mapper->toTypeScript('integer'))->toBe('number')
        ->and($mapper->toTypeScript('bool'))->toBe('boolean')
        ->and($mapper->toTypeScript('boolean'))->toBe('boolean')
        ->and($mapper->toTypeScript('string'))->toBe('string')
        ->and($mapper->toTypeScript('decimal:2'))->toBe('number');
});

it('maps custom casts configured via overrides', function (): void {
    $mapper = new CastTypeMapper([
        'App\Casts\MoneyCast' => 'number',
        '\App\Casts\CustomObjectCast' => 'MyCustomObject',
    ]);

    expect($mapper->toTypeScript('App\Casts\MoneyCast'))->toBe('number')
        ->and($mapper->toTypeScript('\App\Casts\MoneyCast'))->toBe('number')
        ->and($mapper->toTypeScript('App\Casts\CustomObjectCast'))->toBe('MyCustomObject')
        ->and($mapper->toTypeScript('\App\Casts\CustomObjectCast'))->toBe('MyCustomObject');
});

it('falls back to unknown for unconfigured custom classes', function (): void {
    $mapper = new CastTypeMapper;
    expect($mapper->toTypeScript('NonExistentCustomCast'))->toBe('unknown');
});

it('supports programmatic custom cast registration', function (): void {
    $mapper = new CastTypeMapper;
    $mapper->register('App\Casts\UUIDCast', 'string');

    expect($mapper->toTypeScript('App\Casts\UUIDCast'))->toBe('string')
        ->and($mapper->toTypeScript('\App\Casts\UUIDCast'))->toBe('string');
});
