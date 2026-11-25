# Getting Started

Laravel TypeGen is a lightweight PHP package designed to turn your Eloquent models, Enums, FormRequests, and API Resources into a single, clean, or split TypeScript definitions file. It requires only `nikic/php-parser` to perform AST analysis on API resources and keeps your Laravel backend and Inertia frontend synchronized perfectly.

---

## Technical Design: How It Works

TypeGen uses a hybrid multi-layer inspection technique to resolve database schema, runtime PHP classes, and request validation states into TypeScript schemas:

| Component | Extraction Method | Target Output |
| :--- | :--- | :--- |
| **Eloquent Models** | Evaluates model metadata, legacy/modern accessors, custom cast interfaces, and inspects database columns directly from the active connection. | `interface` or `type` |
| **Relationships** | Follows model declarations via BFS graph walk to resolve deep relation nesting, including polymorphic morph maps. | Optional object graphs |
| **Form Requests** | Statically analyzes `rules()` return structure to capture required, nested (`*`), nullable, and enum validations. | Request payload DTOs |
| **API Resources** | Uses static Abstract Syntax Tree (AST) analysis via `nikic/php-parser` on `toArray()` to capture conditional elements. | API response schemas |
| **Routes** | Inspects Named Routes, reflection of controller signatures, and Eloquent bound model types. | Strict route mappings |

---

## Requirements

- **PHP**: `^8.2`, `^8.3`, or `^8.4`
- **Laravel**: `^11.0`, `^12.0`, or `^13.0`
- **Database**: Active migrated database connection (or pass `--skip-db-check` for DB-less environments)
- **Node.js** (Optional): `>= 18` (CI tests Node 20) for Vite or frontend toolchains.

---

## 1. Install the Package

Install via Composer:

```bash
composer require hemilrajput/laravel-typegen
```

Next, publish the configuration file:

```bash
php artisan vendor:publish --tag=typegen-config
```

This will create a configuration file at `config/typegen.php`.

---

## 2. Configuration Reference

The published `config/typegen.php` configuration provides a simple but powerful customization layer:

| Key | Default | Description |
| :--- | :--- | :--- |
| `scan_mode` | `'attribute'` | Can be `'attribute'` (only scans classes marked with `#[TypeScript]`) or `'all'` (scans every class in paths). |
| `paths` | `app_path('Models')`, etc. | Directories containing target classes to scan. |
| `output.path` | `resources/js/types/generated.ts` | The output path where generated types will be written. |
| `output.split` | `false` | Split output into per-type files (`Models/`, `Enums/`, `Requests/`, `Resources/`) with barrel `index.ts`. |
| `output.routes_path` | `resources/js/types/routes.ts` | The output path for route parameter mappings. |
| `output.style` | `'interface'` | Type style: `'interface'` or `'type'`. |
| `output.type_only_imports` | `false` | Emit `import type { ... }` in split mode for `tsconfig` `verbatimModuleSyntax` compatibility. |
| `output.zod` | `false` | When `true`, compiles Zod validation schemas alongside TypeScript types. |
| `output.banner` | `'// AUTO-GENERATED...'` | Header comment prepended to generated files. |
| `include_timestamps`| `true` | Includes `created_at` and `updated_at` automatically. |
| `include_hidden` | `false` | When `false`, excludes columns listed in your model's `$hidden` property. |
| `nullable_by_default` | `false` | Marks fallback fields nullable when live DB schema inspection is unavailable. |
| `strict_schema` | `true` | Throws `RuntimeException` if a database table does not exist. Set `false` for DB-less CI builds. |
| `routes.ziggy` | `true` | When `true`, emits `declare module 'ziggy-js'` with typed `RouteList` and `RouteParamsMap`. |
| `relations.wrap_with_relation` | `true` | Wraps relationship properties in optional helpers for clarity. |
| `relations.casing` | `'camel'` | Casing for relation properties: `'camel'`, `'pascal'`, `'snake'`, or `'kebab'`. |
| `hooks` | `[]` | Shell commands under `pre_generate` / `post_generate` supporting `{file}` placeholder. |

---

### Command Flags

| Flag | Description |
| :--- | :--- |
| `--dry-run` | Output generated TypeScript to console without writing to disk. |
| `--check` | Fail with exit code 1 if generated types do not match files on disk (ideal for CI pipelines). |
| `--skip-db-check` | Bypass strict table existence checks and fallback to `$fillable`, `$casts`, and PHPDoc types. |
| `--only` | Comma-separated sources to generate (`enums,requests,resources,models`). |
| `--watch` | Watch files and auto-regenerate on changes in a fresh subprocess. |

---

## 3. Quick Start

To generate types for a model, annotate the class using the `#[TypeScript]` attribute.

### 1. Annotate your model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Hemilrajput\TypeGen\Attributes\TypeScript;

#[TypeScript]
class User extends Model
{
    protected $fillable = ['name', 'email'];
}
```

### 2. Run the generator

Run the Artisan generation command:

```bash
php artisan typescript:generate
```

### 3. Generated Output

By default, this will write a typescript definitions file to `resources/js/types/generated.ts`:

```typescript
// AUTO-GENERATED by laravel-typegen — do not edit by hand.

export interface User {
  readonly id: number;
  name: string;
  email: string;
  readonly created_at: string | null;
  readonly updated_at: string | null;
}
```

