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
* **Required** fields (`required`) are generated as non-optional keys in TS (e.g. `title: string;`).
* **Required & Nullable**: Decoupled cleanly. Rules like `['required', 'nullable', 'string']` generate mandatory keys with nullable union values (`field: string | null;`) and `z.string().nullable()` rather than making the key optional.
* **Nullable** fields (`nullable`) are marked with `| null`.
* **Optional/Sometimes** fields (`sometimes` or not marked `required`) are generated with a `?` modifier in TS (e.g. `field?: string | null;`).
* **Enum validation rules** (like `new Enum(...)`) are automatically resolved to their corresponding TypeScript enum type name.
* **Confirmed Fields**: Validations with `confirmed` (e.g. `'password' => ['required', 'confirmed']`) automatically emit `{field}_confirmation` in both the TypeScript interface and matching Zod schema.
* **Password Rule Objects**: Native `Illuminate\Validation\Rules\Password` objects (e.g. `Password::min(12)`) are parsed as `string` and forward minimum length constraints.
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

### Sandboxed Runtime & AST Fallback
FormRequests that depend on request state (e.g., `$this->route('user')` or `$this->user()`) are safely handled:
1. **Container Isolation**: TypeGen binds mock route resolvers and user resolvers into the Laravel container during reflection.
2. **Static AST Analysis Fallback**: If runtime evaluation still throws an exception (such as missing database records or unresolvable services), TypeGen automatically falls back to Abstract Syntax Tree (AST) extraction via `nikic/php-parser` (`FormRequestAstVisitor`) so type generation never crashes.

### Zod Schemas & Inferred Payload Types
If you enable Zod schema generation (`'zod' => true` in config), TypeGen emits Zod schemas alongside your interfaces for runtime client-side validation, as well as inferred payload type aliases:

```typescript
export interface StorePostRequest {
  title: string;
  body: string;
}

export const StorePostRequestSchema = z.object({
  title: z.string().max(120),
  body: z.string(),
});

export type StorePostRequestPayload = z.infer<typeof StorePostRequestSchema>;
```

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
- Types are inferred dynamically from the properties accessed (e.g. `$this->id`, `$this->is_active`) and validated against your database schema columns.
- Conditional fields wrapped in `$this->when()` or `$this->whenLoaded()` are automatically marked as optional (`?`) in the generated TypeScript.
- Nested resources (`PostResource::collection(...)` or `new PostResource(...)`) correctly link to their respective TypeScript interfaces.
- **Intelligent `whenLoaded()` Typing**: When calling `$this->whenLoaded('relation')`, TypeGen resolves the relation on the underlying model and generates typed targets (e.g. `Post[] | null` or `PostResource[] | null`) instead of defaulting to `any`.
- **Custom Callback Evaluation**: Closures or arrow functions passed as the second argument to `whenLoaded('relation', fn () => ...)` are inspected and accurately typed.
- **Explicit Model Association**: For resources with non-standard names, specify the underlying model using `#[TypeScript(model: User::class)]` or PHPDoc `@mixin User`.
