<?php

namespace Hemilrajput\TypeGen\Tests\Feature;

use Hemilrajput\TypeGen\Attributes\TypeScript;
use Hemilrajput\TypeGen\Generators\ModelGenerator;
use Hemilrajput\TypeGen\Mappers\CastTypeMapper;
use Hemilrajput\TypeGen\Relations\RelationDetector;
use Hemilrajput\TypeGen\Relations\RelationResolver;
use Hemilrajput\TypeGen\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

uses(TestCase::class);

#[TypeScript(includeRelations: ['blogPosts', 'userProfile'])]
class AuthorWithCasedRelations extends Model
{
    public function blogPosts(): HasMany
    {
        return $this->hasMany(CasedPost::class);
    }

    public function userProfile(): HasOne
    {
        return $this->hasOne(CasedProfile::class);
    }
}

class CasedPost extends Model {}

class CasedProfile extends Model {}

function buildModelGenerator(array $configOverrides = []): ModelGenerator
{
    $config = array_replace_recursive(config('typegen'), [
        'strict_schema' => false,
    ], $configOverrides);

    return new ModelGenerator(
        new CastTypeMapper,
        new RelationResolver(new RelationDetector),
        $config
    );
}

it('defaults to camelCase for relation names', function (): void {
    $generator = buildModelGenerator();
    $result = $generator->generate(AuthorWithCasedRelations::class);

    expect($result['block'])
        ->toContain('  blogPosts?: Relation<CasedPost[]>;')
        ->and($result['block'])->toContain('  userProfile?: Relation<CasedProfile | null>;');
});

it('supports PascalCase casing for relation names', function (): void {
    $generator = buildModelGenerator([
        'relations' => [
            'casing' => 'pascal',
        ],
    ]);
    $result = $generator->generate(AuthorWithCasedRelations::class);

    expect($result['block'])
        ->toContain('  BlogPosts?: Relation<CasedPost[]>;')
        ->and($result['block'])->toContain('  UserProfile?: Relation<CasedProfile | null>;');
});

it('supports snake_case casing for relation names', function (): void {
    $generator = buildModelGenerator([
        'relations' => [
            'casing' => 'snake',
        ],
    ]);
    $result = $generator->generate(AuthorWithCasedRelations::class);

    expect($result['block'])
        ->toContain('  blog_posts?: Relation<CasedPost[]>;')
        ->and($result['block'])->toContain('  user_profile?: Relation<CasedProfile | null>;');
});

it('supports kebab-case casing with identifier quotes for relation names', function (): void {
    $generator = buildModelGenerator([
        'relations' => [
            'casing' => 'kebab',
        ],
    ]);
    $result = $generator->generate(AuthorWithCasedRelations::class);

    expect($result['block'])
        ->toContain("  'blog-posts'?: Relation<CasedPost[]>;")
        ->and($result['block'])->toContain("  'user-profile'?: Relation<CasedProfile | null>;");
});

it('supports varied casing alias strings like PascalCase, snake_case, and kebab-case', function (): void {
    $pascalGen = buildModelGenerator(['relations' => ['casing' => 'PascalCase']]);
    expect($pascalGen->generate(AuthorWithCasedRelations::class)['block'])
        ->toContain('  BlogPosts?: Relation<CasedPost[]>;');

    $snakeGen = buildModelGenerator(['relations' => ['casing' => 'snake_case']]);
    expect($snakeGen->generate(AuthorWithCasedRelations::class)['block'])
        ->toContain('  blog_posts?: Relation<CasedPost[]>;');

    $kebabGen = buildModelGenerator(['relations' => ['casing' => 'kebab-case']]);
    expect($kebabGen->generate(AuthorWithCasedRelations::class)['block'])
        ->toContain("  'blog-posts'?: Relation<CasedPost[]>;");
});

it('works alongside relations wrap_with_relation disabled', function (): void {
    $generator = buildModelGenerator([
        'relations' => [
            'wrap_with_relation' => false,
            'casing' => 'snake',
        ],
    ]);
    $result = $generator->generate(AuthorWithCasedRelations::class);

    expect($result['block'])
        ->toContain('  blog_posts?: CasedPost[];')
        ->and($result['block'])->toContain('  user_profile?: CasedProfile | null;');
});
