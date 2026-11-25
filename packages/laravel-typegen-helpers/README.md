# @hemilrajput/laravel-typegen-helpers

TypeScript utility types for the [Laravel TypeGen](https://github.com/hemilrajput/laravel-typegen) ecosystem.

## Install

```bash
npm install @hemilrajput/laravel-typegen-helpers
# or
pnpm add @hemilrajput/laravel-typegen-helpers
```

## Utilities

### `PaginatedResponse<T>` / `CursorPaginatedResponse<T>`

Mirrors `Model::paginate()` and `Model::cursorPaginate()` JSON shapes.

```ts
import type { PaginatedResponse } from '@hemilrajput/laravel-typegen-helpers';

const res: PaginatedResponse<User> = await fetch('/api/users').then(r => r.json());
console.log(res.meta.total);
```

### `InertiaForm<T>`

Typed wrapper for Inertia's `useForm()`.

```ts
import type { InertiaForm } from '@hemilrajput/laravel-typegen-helpers';
import { useForm } from '@inertiajs/vue3';

const form: InertiaForm<{ name: string; email: string }> = useForm({ name: '', email: '' });
```

### `ApiResource<T>` / `ApiResourceCollection<T>`

Envelope types for Laravel `JsonResource` responses.

```ts
import type { ApiResource } from '@hemilrajput/laravel-typegen-helpers';

const res: ApiResource<User> = await fetch('/api/users/1').then(r => r.json());
```

### `Relation<T>` & `isRelationLoaded()`

Mirrors the `Relation<T>` helper emitted by laravel-typegen when `relations.wrap_with_relation` is enabled, with a runtime narrowing guard.

```ts
import { isRelationLoaded, unwrapResource } from '@hemilrajput/laravel-typegen-helpers';
import type { Relation } from '@hemilrajput/laravel-typegen-helpers';

interface User {
    id: number;
    posts?: Relation<Post[]>;
}

if (isRelationLoaded(user.posts)) {
    // user.posts is narrowed to Post[]
}
```

### Utility Types

| Type | Description |
|---|---|
| `DeepPartial<T>` | Recursively makes all fields optional |
| `RequireFields<T, K>` | Makes specific keys required |
| `Unarray<T>` | Extracts element type from array |
| `EnumRecord<K, V>` | Record keyed by enum/union string |

## License

MIT
