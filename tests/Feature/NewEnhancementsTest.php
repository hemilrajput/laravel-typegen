<?php

namespace Hemilrajput\TypeGen\Tests\Feature;

use Hemilrajput\TypeGen\Attributes\TypeScript;
use Hemilrajput\TypeGen\Generators\FormRequestGenerator;
use Hemilrajput\TypeGen\Generators\ModelGenerator;
use Hemilrajput\TypeGen\Generators\ResourceGenerator;
use Hemilrajput\TypeGen\Mappers\CastTypeMapper;
use Hemilrajput\TypeGen\Mappers\RuleToTypeMapper;
use Hemilrajput\TypeGen\Mappers\RuleTree;
use Hemilrajput\TypeGen\Relations\RelationDetector;
use Hemilrajput\TypeGen\Relations\RelationResolver;
use Hemilrajput\TypeGen\Tests\Fixtures\Models\User;
use Hemilrajput\TypeGen\Tests\Fixtures\Resources\PostResource;
use Hemilrajput\TypeGen\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;

uses(TestCase::class);

#[TypeScript(model: User::class)]
class UserDetailsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'posts' => $this->whenLoaded('posts'),
            'posts_custom' => $this->whenLoaded('posts', fn () => PostResource::collection($this->posts)),
            'profile' => $this->whenLoaded('profile'),
        ];
    }
}

#[TypeScript]
class RegisterUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ];
    }
}

#[TypeScript]
class UnmigratedItem extends Model
{
    protected $table = 'unmigrated_items';

    protected $fillable = ['title', 'content'];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
        ];
    }
}

it('resolves whenLoaded relationships and custom callbacks in API Resources', function (): void {
    $outputPath = sys_get_temp_dir().'/resource_whenloaded_test.ts';
    config()->set('typegen.paths.resources', sys_get_temp_dir());
    config()->set('typegen.output.path', $outputPath);

    $generator = new ResourceGenerator(
        new CastTypeMapper,
        config('typegen'),
    );
    $content = $generator->generate(UserDetailsResource::class);

    expect($content)->toContain('export interface UserDetailsResource')
        ->and($content)->toContain('id: number;')
        ->and($content)->toContain('name: string;')
        ->and($content)->toContain('posts?: Post[];')
        ->and($content)->toContain('posts_custom?: PostResource[];')
        ->and($content)->toContain('profile?: Profile | null;');
});

it('automatically expands confirmed rule into confirmation field and respects Password rule object', function (): void {
    $outputPath = sys_get_temp_dir().'/confirmed_request_test.ts';
    config()->set('typegen.output.path', $outputPath);
    config()->set('typegen.output.zod', true);

    $generator = new FormRequestGenerator(
        new RuleToTypeMapper,
        new RuleTree,
        config('typegen'),
    );
    $content = $generator->generate(RegisterUserRequest::class);

    // TypeScript interface contains both password and password_confirmation
    expect($content)->toContain('export interface RegisterUserRequest')
        ->and($content)->toContain('password: string;')
        ->and($content)->toContain('password_confirmation: string;')
        // Zod schema contains both with .min(12) constraint extracted from Password object
        ->and($content)->toContain('export const RegisterUserRequestSchema')
        ->and($content)->toContain('password: z.string().min(12),')
        ->and($content)->toContain('password_confirmation: z.string().min(12),');
});

it('throws RuntimeException when database table does not exist in strict mode', function (): void {
    $outputPath = sys_get_temp_dir().'/strict_db_test.ts';
    config()->set('typegen.output.path', $outputPath);
    config()->set('typegen.strict_schema', true);

    $generator = new ModelGenerator(
        new CastTypeMapper,
        new RelationResolver(new RelationDetector),
        config('typegen'),
    );

    expect(fn (): array => $generator->generate(UnmigratedItem::class))
        ->toThrow(\RuntimeException::class, 'Table [unmigrated_items] does not exist');
});

it('gracefully generates types without migrated table when skip-db-check is used or strict_schema is false', function (): void {
    $outputPath = sys_get_temp_dir().'/skip_db_test.ts';
    config()->set('typegen.output.path', $outputPath);
    config()->set('typegen.strict_schema', false);

    $generator = new ModelGenerator(
        new CastTypeMapper,
        new RelationResolver(new RelationDetector),
        config('typegen'),
    );
    $result = $generator->generate(UnmigratedItem::class);

    expect($result['block'])->toContain('export interface UnmigratedItem')
        ->and($result['block'])->toContain('readonly id: number;')
        ->and($result['block'])->toContain('title: string;')
        ->and($result['block'])->toContain('content: string;')
        ->and($result['block'])->toContain('is_archived: boolean;')
        ->and($result['block'])->toContain('readonly created_at: string | null;')
        ->and($result['block'])->toContain('readonly updated_at: string | null;');
});

it('emits RouteParamsMap and Ziggy module declaration when ziggy routes are enabled', function (): void {
    Route::get('/users', fn (): string => 'index')->name('users.index');
    Route::get('/users/{user}', fn (User $user): string => 'show')->name('users.show');

    $outputPath = sys_get_temp_dir().'/ziggy_routes_test.ts';
    config()->set('typegen.output.routes_path', $outputPath);
    config()->set('typegen.routes.ziggy', true);

    $this->artisan('typescript:routes')->assertSuccessful();

    $contents = file_get_contents($outputPath);

    expect($contents)->toContain('export type RouteName =')
        ->and($contents)->toContain('export type RouteParamsMap = {')
        ->and($contents)->toContain("'users.index': {};")
        ->and($contents)->toContain("'users.show': { user: number };")
        ->and($contents)->toContain("declare module 'ziggy-js' {")
        ->and($contents)->toContain('interface RouteList {')
        ->and($contents)->toContain("'users.index': [];")
        ->and($contents)->toContain("'users.show': [{ name: 'user', required: true }];");

    @unlink($outputPath);
});
