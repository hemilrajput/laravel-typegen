# Laravel TypeGen

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hemilrajput/laravel-typegen.svg)](https://packagist.org/packages/hemilrajput/laravel-typegen)
[![Total Downloads](https://img.shields.io/packagist/dt/hemilrajput/laravel-typegen.svg)](https://packagist.org/packages/hemilrajput/laravel-typegen)
[![Tests](https://github.com/hemilrajput/laravel-typegen/actions/workflows/tests.yml/badge.svg)](https://github.com/hemilrajput/laravel-typegen/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE.md)

> **Zero-DB CI Extraction + Automatic Zod Validation Schemas & TypeScript Types** directly from Eloquent models, Enums, FormRequests, and API Resources. Built for the modern Laravel + Inertia (React/Vue) stack.

## 📖 Documentation
For complete documentation, guides, and setup instructions, visit [hemilrajput.github.io/laravel-typegen](https://hemilrajput.github.io/laravel-typegen/).

## Why Laravel TypeGen?
- **Zero-DB CI Extraction**: Generate strict TypeScript interfaces in offline CI pipelines (`--skip-db-check`) without spinning up database containers.
- **Automatic Zod Validation**: Compile FormRequest `rules()` directly into frontend Zod schemas (`z.object({...})`) and inferred payloads (`z.infer`).
- **Synchronized Architecture**: Automatically link PHP Enums to Model casts and relationship graphs across frontend and backend.

---

## ⚡️ The Killer Features

### 1. Synchronized PHP Enum + Model Casts
```php
enum UserRole: string
{
    case Admin = 'admin';
}

class User extends Model
{
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
        ];
    }
}
```

```ts
export type UserRole = 'admin';

export interface User {
  readonly id: number;
  role: UserRole; // Automatically linked!
}
```

### 2. FormRequest → TypeScript DTO & Zod Schema (Zero-DB CI)
```php
#[TypeScript]
class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'status' => ['required', new Enum(PostStatus::class)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
        ];
    }
}
```

```ts
export interface StorePostRequest {
  title: string;
  status: PostStatus;
  tags?: string[] | null;
}

export const StorePostRequestSchema = z.object({
  title: z.string().max(120),
  status: z.enum(['draft', 'published']),
  tags: z.array(z.string()).nullish(),
});

export type StorePostRequestPayload = z.infer<typeof StorePostRequestSchema>;
```

---

## 🚀 Features
- **Eloquent Models**: Generates interfaces from `$fillable`, `$casts`, and timestamps.
- **Enums**: Generates union types from backed and pure PHP enums.
- **FormRequests & Zod**: Generates request DTOs from your `rules()` method and compiles them to Zod schemas for client-side validation.
- **API Resources**: Transforms Laravel JsonResources into TypeScript representations.
- **Routes & Ziggy**: Generates strict types for named routes.
- **VS Code Extension**: Auto-generates types on file save.
- **NPM Helpers**: First-party `@hemilrajput/laravel-typegen-helpers` package for `PaginatedResponse`, `InertiaForm`, etc.
- **Attribute-Driven**: Opt-in to generation using the `#[TypeScript]` attribute.
- **Zero-Config**: Smart defaults for standard Laravel projects.

## 📊 Comparison

| Feature | TypeGen | Spatie TS Transformer |
|---|:---:|:---:|
| Eloquent Support | ✅ | ✅ |
| Enum Support | ✅ | ✅ |
| **FormRequest → DTO & Zod** | ✅ | ❌ |
| **API Resources** | ✅ | ❌ |
| **Relationship Auto-Discovery** | ✅ | ❌ |
| **Linked Enum Casts** | ✅ | ⚠️ (Manual) |
| Attribute Driven | ✅ | ✅ |
| Inertia Native & NPM Helpers | ✅ | ⚠️ |
| VS Code Extension | ✅ | ❌ |

---

## 📦 Installation
```bash
composer require hemilrajput/laravel-typegen
```
*Requires PHP `^8.2` and Laravel `^11.0|^12.0|^13.0`.*

## 🛠 Usage

### 1. Tag your classes
```php
use Hemilrajput\TypeGen\Attributes\TypeScript;

#[TypeScript]
class User extends Model { ... }
```

### 2. Generate
```bash
php artisan typescript:generate
```

---

## 🔗 Relationships

Opt into relationship type generation per-model:

```php
#[TypeScript(includeRelations: ['posts', 'profile'])]
class User extends Model { /* ... */ }
```

Related models (`Post`, `Profile`) are **auto-discovered** — no need to mark them separately. Generated output:

```ts
export interface User {
  id: number;
  posts?: Post[];
  profile?: Profile | null;
}

export interface Post { /* ... */ }
export interface Profile { /* ... */ }
```

Relations are always emitted as **optional** (`?`) because they're only present when eager-loaded. This matches runtime reality.

### Polymorphic relations

`MorphTo` is auto-supported when you register a morph map:

```php
Relation::enforceMorphMap([
    'post' => Post::class,
    'video' => Video::class,
]);
```

Generates:

```ts
export interface Comment {
  commentable?: (Post | Video) | null;
}
```

Without a morph map, emits `unknown | null` with a comment.

---

## 🗺 Roadmap

- [x] **v2.3.1**: AST Resource extraction, DB-less CI fallback (`--skip-db-check`), Ziggy route typing.
- [x] **v2.3.2**: Release process hardening (gated tag CI), security policy, metadata consistency, and `.gitattributes`.
- [x] **v2.4.0**: Required/nullable decoupling fix, sandboxed FormRequest evaluation & AST static extraction fallback, inferred Zod payload types (`z.infer`).
- [ ] **v2.5.0**: Eloquent relationship depth (pivot table typing for BelongsToMany, withCount/withExists virtual attributes, BelongsTo nullability resolution).
- [ ] **v3.0.0-rc.1 / v3.0.0**: Minimum PHP 8.3, Laravel 12/13 baseline, opt-in/auto-detected Ziggy vs Wayfinder interop, frozen public API.

## Configuration

See `config/typegen.php` for all available options, including:
- Output path and style (interface vs type).
- Custom cast mapping.
- Prefix/suffix for generated names.
- Including/excluding timestamps and hidden fields.

## License
MIT
