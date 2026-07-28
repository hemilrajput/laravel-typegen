# Laravel TypeGen

> **Laravel TypeGen** — one artisan command turns your Eloquent models, Enums, and FormRequests into a single typed `.ts` file. No more hand-syncing PHP and TypeScript.

## 📖 Documentation
For complete documentation, guides, and setup instructions, visit [laravel-typegen.github.io](https://hemilrajput.github.io/laravel-typegen/).

## Why Laravel TypeGen?
- **Keeps types in sync**: Automatically reflect changes in your PHP models in your TypeScript interfaces.
Generate TypeScript types from Eloquent models, Enums, and FormRequests. Built for the Laravel 13 + Inertia + React/Vue stack.

---

## ⚡️ The Killer Feature: Synchronized Types
Laravel TypeGen doesn't just generate standalone interfaces. It understands your application's logic.

**PHP Enum + Model Cast:**
```php
enum UserRole: string {
    case Admin = 'admin';
}

class User extends Model {
    protected $casts = ['role' => UserRole::class];
}
```

**TypeScript Output:**
```ts
export type UserRole = 'admin';

export interface User {
    id: number;
    role: UserRole; // Automatically linked!
}
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
- [x] Enum support (v0.2)
- [x] FormRequest → DTO (v0.2)
- [x] Eloquent relationships (v0.3)
- [x] Route parameter types (v0.4)
- [x] Watch mode (v0.4)
- [x] Custom Cast class resolver (v0.4)
- [x] VitePress documentation site (v1.0.0)
- [x] Stable release (v1.0.0)
- [x] API Resource generation (v1.3.0)
- [x] VS Code Extension & NPM package (v2.0.0)
- [x] Zod Schema Compilation (v2.2.0)
- [ ] Resource AST Parser for robust types
- [ ] Zod schema advanced constraints

## Configuration

See `config/typegen.php` for all available options, including:
- Output path and style (interface vs type).
- Custom cast mapping.
- Prefix/suffix for generated names.
- Including/excluding timestamps and hidden fields.

## License
MIT
