<?php

declare(strict_types=1);

namespace Hemilrajput\TypeGen\Tests\Feature;

use Hemilrajput\TypeGen\Attributes\TypeScript;
use Hemilrajput\TypeGen\Attributes\TypeScriptIgnore;
use Hemilrajput\TypeGen\Generators\FormRequestGenerator;
use Hemilrajput\TypeGen\Generators\ModelGenerator;
use Hemilrajput\TypeGen\Generators\ResourceGenerator;
use Hemilrajput\TypeGen\Mappers\CastTypeMapper;
use Hemilrajput\TypeGen\Mappers\RuleToTypeMapper;
use Hemilrajput\TypeGen\Mappers\RuleTree;
use Hemilrajput\TypeGen\Relations\RelationDetector;
use Hemilrajput\TypeGen\Relations\RelationResolver;
use Hemilrajput\TypeGen\Scanners\ClassScanner;
use Hemilrajput\TypeGen\Tests\Fixtures\Enums\PostStatus;
use Hemilrajput\TypeGen\Tests\Fixtures\Models\Post;
use Hemilrajput\TypeGen\Tests\Fixtures\Models\User;
use Hemilrajput\TypeGen\Tests\TestCase;
use Hemilrajput\TypeGen\Writers\TypeScriptSplitWriter;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

uses(TestCase::class);

/**
 * @property PostStatus $status
 * @property Post[] $custom_posts
 */
#[TypeScript(includeRelations: ['articles'])]
class PrefixedAuthor extends Model
{
    protected $table = 'users';

    protected $fillable = ['name'];

    protected $appends = ['visible_label', 'ignored_secret'];

    public function articles(): HasMany
    {
        return $this->hasMany(PrefixedArticle::class);
    }

    protected function visibleLabel(): Attribute
    {
        return Attribute::make(get: fn (): string => 'ok');
    }

    #[TypeScriptIgnore]
    protected function ignoredSecret(): Attribute
    {
        return Attribute::make(get: fn (): string => 'secret');
    }
}

class PrefixedArticle extends Model
{
    protected $table = 'posts';
}

#[TypeScript(name: 'RenamedAccountResource', model: User::class)]
class AccountDetailsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'posts_count' => $this->whenCounted('posts'),
            'avg_score' => $this->whenAggregated('posts', 'score', 'avg'),
            'verified_at' => $this->whenNotNull($this->email_verified_at),
        ];
    }
}

#[TypeScript]
class AstVariableAndEnumRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->route('missing')->nonExistent()) {
            return [];
        }

        return [
            'status' => ['required', new Enum(PostStatus::class)],
            'fallback_status' => ['required', Rule::enum(PostStatus::class)],
            'password' => ['required', 'string', Password::min(10)],
        ];
    }
}

class SlugPost extends Model
{
    protected $table = 'posts';

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

class MorphVideo extends Model {}

#[TypeScript(includeRelations: ['commentable'])]
class MorphComment extends Model
{
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}

function cleanupTempDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            @rmdir($file->getRealPath());
        } else {
            File::delete($file->getRealPath());
        }
    }
    @rmdir($dir);
}

it('preserves PascalCase PHPDoc types, respects include_timestamps in DB mode, ignores #[TypeScriptIgnore] accessors, and applies model prefix/suffix in relations', function (): void {
    $config = array_replace_recursive(config('typegen'), [
        'include_timestamps' => false,
        'naming' => [
            'model_prefix' => 'I',
            'model_suffix' => 'Model',
        ],
    ]);

    $generator = new ModelGenerator(
        new CastTypeMapper,
        new RelationResolver(new RelationDetector),
        $config
    );

    $result = $generator->generate(PrefixedAuthor::class);

    expect($result['block'])
        ->toContain('export interface IPrefixedAuthorModel')
        ->toContain('status: PostStatus;')
        ->toContain('custom_posts: Post[];')
        ->toContain('readonly visible_label: string;')
        ->toContain('articles?: Relation<IPrefixedArticleModel[]>;')
        ->not->toContain('ignored_secret')
        ->not->toContain('created_at')
        ->not->toContain('updated_at');
});

it('applies nullable_by_default in fallback schema mode', function (): void {
    $config = array_replace_recursive(config('typegen'), [
        'strict_schema' => false,
        'nullable_by_default' => true,
    ]);

    $generator = new ModelGenerator(
        new CastTypeMapper,
        new RelationResolver(new RelationDetector),
        $config
    );

    $result = $generator->generate(UnmigratedItem::class);

    expect($result['block'])
        ->toContain('title: string | null;')
        ->toContain('is_archived: boolean | null;');
});

it('supports #[TypeScript(name: ...)] and whenCounted/whenAggregated/whenNotNull in API Resources', function (): void {
    $generator = new ResourceGenerator(new CastTypeMapper, config('typegen'));
    $output = $generator->generate(AccountDetailsResource::class);

    expect($output)
        ->toContain('export interface RenamedAccountResource')
        ->toContain('posts_count?: number;')
        ->toContain('avg_score?: number | null;')
        ->toContain('verified_at?: string;');
});

it('resolves Enum, Rule::enum, Password::min, and local variable return in FormRequest AST fallback', function (): void {
    $generator = new FormRequestGenerator(new RuleToTypeMapper, new RuleTree, array_replace_recursive(config('typegen'), [
        'output' => ['zod' => true],
    ]));

    $output = $generator->generate(AstVariableAndEnumRequest::class);

    expect($output)
        ->toContain('status: PostStatus;')
        ->toContain('fallback_status: PostStatus;')
        ->toContain('password: string;')
        ->toContain("status: z.enum(['draft', 'published']),")
        ->toContain('password: z.string().min(10),');
});

it('extracts rules assigned to a local variable in FormRequest AST fallback', function (): void {
    $tempFile = sys_get_temp_dir().'/AstVarFallbackRequest_'.uniqid().'.php';
    file_put_contents($tempFile, <<<'PHP'
<?php
namespace Hemilrajput\TypeGen\Tests\TempAst;
use Illuminate\Foundation\Http\FormRequest;
class AstVarFallbackRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->route('x')->boom()) {
            return [];
        }
        $rules = [
            'title' => ['required', 'string'],
        ];
        return $rules;
    }
}
PHP);
    require_once $tempFile;

    $generator = new FormRequestGenerator(new RuleToTypeMapper, new RuleTree, config('typegen'));
    $output = $generator->generate('Hemilrajput\TypeGen\Tests\TempAst\AstVarFallbackRequest');

    expect($output)->toContain('title: string;');

    File::delete($tempFile);
});

it('injects zod import, MorphTo union imports, and import type in split mode', function (): void {
    $baseOut = sys_get_temp_dir().'/split_zod_morph_'.uniqid().'.ts';
    $dir = dirname($baseOut).'/'.pathinfo($baseOut, PATHINFO_FILENAME);

    $config = array_replace_recursive(config('typegen'), [
        'strict_schema' => false,
        'output' => [
            'path' => $baseOut,
            'split' => true,
            'zod' => true,
            'type_only_imports' => true,
        ],
    ]);

    Relation::enforceMorphMap([
        'post' => Post::class,
        'video' => MorphVideo::class,
    ]);

    $modelGen = new ModelGenerator(new CastTypeMapper, new RelationResolver(new RelationDetector), $config);
    $requestGen = new FormRequestGenerator(new RuleToTypeMapper, new RuleTree, $config);

    $writer = new TypeScriptSplitWriter($config);
    $writer->write([
        ['category' => 'Imports', 'content' => "import { z } from 'zod';"],
        ['category' => 'Enums', 'content' => "export type PostStatus = 'draft' | 'published';"],
        ['category' => 'Models', 'content' => $modelGen->generate(Post::class)['block']],
        ['category' => 'Models', 'content' => $modelGen->generate(MorphVideo::class)['block']],
        ['category' => 'Models', 'content' => $modelGen->generate(MorphComment::class)['block']],
        ['category' => 'Requests', 'content' => $requestGen->generate(AstVariableAndEnumRequest::class)],
    ]);

    expect(is_dir("{$dir}/Imports"))->toBeFalse();

    $requestContent = file_get_contents("{$dir}/Requests/AstVariableAndEnumRequest.ts");
    expect($requestContent)
        ->toContain("import { z } from 'zod';")
        ->toContain("import type { PostStatus } from '../Enums/PostStatus';");

    $commentContent = file_get_contents("{$dir}/Models/MorphComment.ts");
    expect($commentContent)
        ->toContain("import type { Post } from './Post';")
        ->toContain("import type { MorphVideo } from './MorphVideo';");

    cleanupTempDir($dir);
});

it('ignores comments, abstract classes, and #[TypeScriptIgnore] classes in ClassScanner', function (): void {
    $tempDir = sys_get_temp_dir().'/scanner_test_'.uniqid();
    mkdir($tempDir, 0755, true);

    file_put_contents("{$tempDir}/CommentTrick.php", <<<'PHP'
<?php
namespace Hemilrajput\TypeGen\Tests\TempScanner;
// class FakeCommentClass {}
use Hemilrajput\TypeGen\Attributes\TypeScriptIgnore;
#[TypeScriptIgnore]
class IgnoredScannerClass {}
PHP);

    file_put_contents("{$tempDir}/AbstractBase.php", <<<'PHP'
<?php
namespace Hemilrajput\TypeGen\Tests\TempScanner;
abstract class AbstractBase {}
PHP);

    file_put_contents("{$tempDir}/ValidScanned.php", <<<'PHP'
<?php
namespace Hemilrajput\TypeGen\Tests\TempScanner;
class ValidScanned {}
PHP);

    require_once "{$tempDir}/CommentTrick.php";
    require_once "{$tempDir}/AbstractBase.php";
    require_once "{$tempDir}/ValidScanned.php";

    $classes = (new ClassScanner)->scan([$tempDir], mode: 'all');

    expect($classes)->toBe(['Hemilrajput\TypeGen\Tests\TempScanner\ValidScanned']);

    cleanupTempDir($tempDir);
});

it('supports --only filter on typescript:generate', function (): void {
    $outputPath = sys_get_temp_dir().'/only_enums_'.uniqid().'.ts';
    config()->set('typegen.paths.enums', __DIR__.'/../Fixtures/Enums');
    config()->set('typegen.paths.models', __DIR__.'/../Fixtures/Models');
    config()->set('typegen.output.path', $outputPath);

    $this->artisan('typescript:generate', ['--only' => 'enums'])->assertSuccessful();

    $contents = file_get_contents($outputPath);
    expect($contents)
        ->toContain('export type PostStatus')
        ->not->toContain('export interface User');

    File::delete($outputPath);
});

it('resolves custom route binding keys and supports --check on typescript:routes', function (): void {
    Route::get('/articles/{post:slug}', fn (Post $post): string => 'ok')->name('articles.show');
    Route::get('/slug-posts/{slugPost}', fn (SlugPost $slugPost): string => 'ok')->name('slug-posts.show');

    $outputPath = sys_get_temp_dir().'/routes_check_'.uniqid().'.ts';
    config()->set('typegen.output.routes_path', $outputPath);

    $this->artisan('typescript:routes')->assertSuccessful();

    $contents = file_get_contents($outputPath);
    expect($contents)
        ->toContain("T extends 'articles.show' ? { post: string } :")
        ->toContain("T extends 'slug-posts.show' ? { slugPost: string } :");

    $this->artisan('typescript:routes', ['--check' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Route types are up to date');

    file_put_contents($outputPath, 'stale');

    $this->artisan('typescript:routes', ['--check' => true])
        ->assertFailed();

    File::delete($outputPath);
});
