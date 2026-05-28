# Migration from Spatie TypeScript Transformer

If you are migrating from `spatie/laravel-typescript-transformer`, this guide walks you through shifting to `laravel-typegen`.

## Comparison of Attributes

| Feature | Spatie | Laravel TypeGen |
|---|---|---|
| Attribute | `#[TypeScript]` | `#[TypeScript]` |
| Attribute Namespace | `Spatie\LaravelTypeScript\...` | `hemilrajput\TypeGen\Attributes\TypeScript` |
| Overriding Names | `#[TypeScript('CustomName')]` | `#[TypeScript(name: 'CustomName')]` |
| Relationship Auto-discovery | No | Yes (opt-in) |
| FormRequest Request DTOs | Complex | Yes (out of the box) |

---

## Migration Steps

### 1. Remove Spatie package
First, remove Spatie's transformer from your project:

```bash
composer remove spatie/laravel-typescript-transformer
```

### 2. Install Laravel TypeGen
Install the package and publish the config:

```bash
composer require hemilrajput/laravel-typegen
php artisan vendor:publish --tag=typegen-config
```

### 3. Update Attribute Imports
Search and replace the Spatie attribute imports with the TypeGen attributes.

Change:
```php
use Spatie\LaravelTypeScriptTransformer\Attributes\TypeScript;
```

To:
```php
use hemilrajput\TypeGen\Attributes\TypeScript;
```

If you were customizing names:
```php
// Old (Spatie)
#[TypeScript('CustomName')]

// New (TypeGen)
#[TypeScript(name: 'CustomName')]
```

### 4. Remove Spatie config
Delete the old configuration file:
```bash
rm config/typescript-transformer.php
```

### 5. Generate new types
Run the generation command:
```bash
php artisan typescript:generate
```
Update your frontend import paths to point to the new output path (`resources/js/types/generated.ts` or the split folder `resources/js/types/generated/`).
