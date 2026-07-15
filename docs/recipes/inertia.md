# Inertia.js Integration

Laravel TypeGen works wonderfully with Inertia.js, giving you end-to-end type safety from your backend Eloquent models directly to your frontend Vue or React components.

## Basic Setup

Once you've generated your types using `php artisan typegen:generate`, you can import them directly into your frontend components.

### React Example

If you are using React, you can type your Inertia page props by importing the generated model types.

```tsx
import React from 'react';
import { User, Post } from '@/types/generated';
import { Head, Link } from '@inertiajs/react';

interface Props {
  user: User;
  posts: Post[];
}

export default function Dashboard({ user, posts }: Props) {
  return (
    <div>
      <Head title="Dashboard" />
      <h1>Welcome back, {user.name}</h1>
      
      <ul>
        {posts.map(post => (
          <li key={post.id}>
            <Link href={`/posts/${post.id}`}>{post.title}</Link>
          </li>
        ))}
      </ul>
    </div>
  );
}
```

### Vue Example

For Vue users, you can use `defineProps` with the generated interfaces to get full IDE autocomplete and type checking in your templates.

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { User, Post } from '@/types/generated';

defineProps<{
  user: User;
  posts: Post[];
}>();
</script>

<template>
  <div>
    <Head title="Dashboard" />
    <h1>Welcome back, {{ user.name }}</h1>
    
    <ul>
      <li v-for="post in posts" :key="post.id">
        <Link :href="`/posts/${post.id}`">{{ post.title }}</Link>
      </li>
    </ul>
  </div>
</template>
```

## Shared Data and Global Props

Inertia allows you to share data across all pages (e.g., the authenticated user). You can type these global props in your frontend application.

First, ensure your `HandleInertiaRequests` middleware shares the user:

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'auth' => [
            'user' => $request->user(),
        ],
    ]);
}
```

Then, define a global type for your Inertia page props (e.g., in a `resources/js/types/index.d.ts` or similar file):

```typescript
import { User } from './generated';

export interface PageProps {
  auth: {
    user: User | null;
  };
  [key: string]: unknown;
}
```

Now you can use this `PageProps` interface across your application to type-safely access global data!
