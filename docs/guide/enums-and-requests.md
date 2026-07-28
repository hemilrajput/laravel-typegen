# Enums, Requests & Resources

TypeScript types for validation payloads and status enums are generated automatically alongside your models.

## Enums

Any PHP 8.1+ Enum marked with `#[TypeScript]` is generated as a TypeScript union type.

```php
namespace App\Enums;

use Hemilrajput\TypeGen\Attributes\TypeScript;

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
use Hemilrajput\TypeGen\Attributes\TypeScript;

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

### Zod Schemas & Advanced Constraints
If you enable Zod schema generation (`'zod' => true` in config), TypeGen will emit Zod schemas alongside your interfaces for runtime client-side validation.

TypeGen extracts advanced validation constraints from your rules to build powerful schemas:
- `email` generates `.email()`
- `min:x` generates `.min(x)`
- `max:x` generates `.max(x)`
- Array constraints like `min:1` generate `.min(1)`

---

## API Resources

Laravel TypeGen supports generating highly accurate interfaces for your `JsonResource` and `ResourceCollection` classes using advanced AST parsing.

```php
use Illuminate\Http\Resources\Json\JsonResource;
use Hemilrajput\TypeGen\Attributes\TypeScript;

#[TypeScript]
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->when($request->user()->isAdmin(), $this->email),
            'posts' => PostResource::collection($this->whenLoaded('posts')),
        ];
    }
}
```

### Advanced AST Parsing

Instead of relying on fragile `@property` docblocks, TypeGen uses `nikic/php-parser` to statically analyze your `toArray()` method's Abstract Syntax Tree (AST):
- Types are inferred dynamically from the properties accessed (e.g. `$this->id`).
- Conditional fields wrapped in `$this->when()` or `$this->whenLoaded()` are automatically marked as optional (`?`) in the generated TypeScript.
- Nested resources (`PostResource::collection(...)` or `new PostResource(...)`) correctly link to their respective TypeScript interfaces.
