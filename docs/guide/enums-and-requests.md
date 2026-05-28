# Enums & Form Requests

TypeScript types for validation payloads and status enums are generated automatically alongside your models.

## Enums

Any PHP 8.1+ Enum marked with `#[TypeScript]` is generated as a TypeScript union type.

```php
namespace App\Enums;

use hemilrajput\TypeGen\Attributes\TypeScript;

#[TypeScript]
enum UserRole: string
{
    case Admin = 'admin';
    case Member = 'member';
}
```

Generated output:
```typescript
export type UserRole = 'admin' | 'member';
```

### Supported Enum Types
* **String-backed enums**: Generated as a union of string literal values (e.g. `'admin' | 'member'`).
* **Integer-backed enums**: Generated as a union of numeric literal values (e.g. `1 | 2`).
* **Pure enums (unbacked)**: Generated as a union of case names as string literals.

### Model Integration
If a model uses an enum in its `$casts` property, the generator resolves and prints the enum type name directly:

```php
protected $casts = [
    'role' => UserRole::class,
];
```

Yields:
```typescript
export interface User {
  role: UserRole;
}
```

---

## Form Requests

FormRequests marked with `#[TypeScript]` are compiled into typed request payload DTO interfaces by analyzing the array returned from `rules()`.

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use hemilrajput\TypeGen\Attributes\TypeScript;

#[TypeScript]
class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string'],
            'status' => ['required', new \Illuminate\Validation\Rules\Enum(PostStatus::class)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
        ];
    }
}
```

Generated output:
```typescript
export interface StorePostRequest {
  title: string;
  body: string;
  status: PostStatus;
  tags?: string[] | null;
}
```

### Rule-to-Type Rules
* **Required** fields (`required`) are generated as non-optional keys in TS.
* **Nullable** fields (`nullable`) are marked with `| null`.
* **Optional/Sometimes** fields (`sometimes` or not marked `required`) are generated with a `?` modifier in TS.
* **Enum validation rules** (like `new Enum(...)`) are automatically resolved to their corresponding TypeScript enum type name.
* **Dot Notation Objects**: Fields like `'author.name' => 'required|string'` are automatically nested into TS objects:
  ```typescript
  author: {
    name: string;
  }
  ```
* **Arrays of Objects**: Nested rules like `items.*.qty` are resolved to array structures:
  ```typescript
  items: {
    qty: number;
  }[]
  ```
