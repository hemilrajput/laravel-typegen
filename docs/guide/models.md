# Eloquent Models

Laravel TypeGen inspects your Eloquent models to generate precise TypeScript interfaces. It automatically maps database column types, model casts, timestamps, hidden attributes, and relationships.

## Column Mapping & Casts

The generator maps Eloquent attributes using the following precedence rules:
1. **Primary Key**: Identified by `getKeyName()`. If `getKeyType()` is `int`, it maps to `number`, otherwise `string`.
2. **Casts**: Attributes listed in `$casts` (or `casts()`) are mapped based on their cast type (e.g. `boolean` to `boolean`, `integer` to `number`).
3. **Fillable attributes**: Columns listed in `$fillable` that are not cast are assumed to be of type `string`.
4. **Timestamps**: If `$timestamps` is enabled, `created_at` and `updated_at` (or custom columns) are automatically generated as `string`.

## Customizing Names

By default, the interface name matches the PHP class name. You can customize the name of the exported interface by passing a parameter to the attribute:

```php
#[TypeScript(name: 'AdminUser')]
class User extends Model {}
```

This will output:
```typescript
export interface AdminUser { ... }
```

## Hidden Attributes

Attributes listed in `$hidden` are omitted from the generated TS interface by default. You can change this behavior in `config/typegen.php` by setting `'include_hidden' => true`.

## Relationships

TypeGen supports auto-generation of Eloquent relationships. You must explicitly opt-in to relations on each model using the `includeRelations` parameter:

```php
#[TypeScript(includeRelations: ['posts', 'profile'])]
class User extends Model
{
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }
}
```

### Auto-Discovery

When you include relationships, TypeGen uses a Breadth-First Search (BFS) graph walk to **auto-discover** and generate the related models (`Post` and `Profile`), even if they aren't marked with the `#[TypeScript]` attribute.

Generated types are automatically marked optional (`?`) with `| null` matching the runtime database nullable state:

```typescript
export interface User {
  id: number;
  posts?: Post[];
  profile?: Profile | null;
}
```

### Polymorphic Relations

Polymorphic `MorphTo` relationships are supported out of the box when a morph map is registered:

```php
// AppServiceProvider.php
Relation::enforceMorphMap([
    'post' => Post::class,
    'video' => Video::class,
]);
```

Which yields:
```typescript
export interface Comment {
  commentable?: (Post | Video) | null;
}
```
If no morph map is registered, the relation defaults to `unknown | null`.
