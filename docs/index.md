---
layout: home

hero:
  name: "Laravel TypeGen"
  text: "TypeScript types, direct from Laravel"
  tagline: "One Artisan command turns your Eloquent models, Enums, and FormRequests into typed TS files. No more hand-syncing PHP and TypeScript."
  actions:
    - theme: brand
      text: Get Started
      link: /guide/getting-started
    - theme: alt
      text: View on GitHub
      link: https://github.com/hemilrajput/laravel-typegen

features:
  - title: Model Generation
    details: Automatically maps Eloquent casts, hidden columns, primary keys, and timestamps to typed TS interfaces.
  - title: Relationship Auto-Discovery
    details: Mark one model and TypeGen will auto-discover and generate all related models using a BFS graph walk.
  - title: FormRequest Request DTOs
    details: Parse validation rules to generate exact request DTO structures, nesting objects and array items cleanly.
  - title: Route Param Safety
    details: Map named route names and their parameters to TS types. Fully compatible with Ziggy.
  - title: Watch Mode
    details: Polling watcher regenerates TS files on change, completely cross-platform with zero native dependencies.
  - title: Custom Cast Mapping
    details: Easily extend the default cast mapper by mapping custom cast classes in configuration.
---
