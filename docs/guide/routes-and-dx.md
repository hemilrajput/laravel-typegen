# Route Parameters & Developer Experience

Advanced DX features make Laravel TypeGen highly customizable, automated, and type-safe.

## Route Parameter Safety

Laravel TypeGen can inspect all named routes registered in your application and generate TypeScript route mappings. This makes navigation parameters completely type-safe when using tools like Ziggy.

Run the routes generation command:

```bash
php artisan typescript:routes
```

By default, this writes to `resources/js/types/routes.ts`:

```typescript
export type RouteName =
  | 'users.index'
  | 'users.show';

export type RouteParams<T extends RouteName> =
  T extends 'users.index' ? {} :
  T extends 'users.show' ? { user: string | number } :
  never;
```

### Optional Parameters
If a route contains an optional parameter (like `{comment?}`), it will be marked optional (`?`) in TS:

```typescript
T extends 'posts.comments' ? { post: string | number; comment?: string | number } :
```

---

## Watch Mode

During active development, manually running the generation command is annoying. You can start TypeGen in watch mode:

```bash
php artisan typescript:generate --watch
```

TypeGen will run in the background, checking the modified times of your models, enums, form requests, and the `typegen.php` config file every second. When any changes are detected, it regenerates the types instantly.

Watch mode is pure PHP, cross-platform, zero-dependency, and lightweight.

---

## File Splitting

By default, all types are emitted to a single file. For large codebases, you might want separate files for each type interface.

Enable splitting in `config/typegen.php`:

```php
'output' => [
    'path' => resource_path('js/types/generated.ts'),
    'split' => true,
],
```

When `split` is true:
1. TypeGen creates a directory called `generated` at `resources/js/types/generated/`.
2. Each type is written to its own file (e.g. `User.ts`, `StorePostRequest.ts`).
3. TypeGen automatically resolves and injects necessary relative imports at the top of each file (e.g. `import { Post } from './Post'`).
4. A central `index.ts` barrel file is created which exports all individual types.

---

## Custom Cast Mappings

If your model casts database fields to custom Cast classes, you can tell TypeGen how to map them to TypeScript types using the `cast_map` key in `config/typegen.php`:

```php
'cast_map' => [
    \App\Casts\MoneyCast::class => 'number',
    \App\Casts\CustomObjectCast::class => 'MyCustomObject',
],
```

This ensures that any model casting to `MoneyCast` yields a type of `number` in TypeScript instead of `unknown`.
