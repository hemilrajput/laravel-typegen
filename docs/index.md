---
layout: home

hero:
  name: "Laravel TypeGen"
  text: "TypeScript types,\ndirect from Laravel"
  tagline: "One Artisan command turns your Eloquent models, Enums, FormRequests & API Resources into fully-typed TypeScript. Zero config. Instant sync. Zero drift."
  image:
    src: /hero-code.svg
    alt: Laravel TypeGen
  actions:
    - theme: brand
      text: Get Started →
      link: /guide/getting-started
    - theme: alt
      text: View on GitHub
      link: https://github.com/hemilrajput/laravel-typegen

features:
  - icon: 🏗️
    title: Model Generation
    details: Deeply inspects Eloquent casts, $appends, hidden columns, primary keys, timestamps, and custom accessors — mapping everything to precise TypeScript interfaces automatically.
    link: /guide/models
    linkText: Learn more

  - icon: 🔗
    title: Relationship Auto-Discovery
    details: Mark one model and TypeGen performs a BFS graph walk to auto-discover and generate all related models, including polymorphic MorphTo unions with enforced morph maps.
    link: /guide/models
    linkText: See relationships

  - icon: 📋
    title: FormRequest DTOs
    details: Parses validation rules to generate exact request DTO interfaces — including nested dot-notation objects, array wildcards, enum rules, and Zod schema compilation.
    link: /guide/enums-and-requests
    linkText: Explore DTOs

  - icon: 🎯
    title: AST-Powered API Resources
    details: Uses nikic/php-parser to statically analyze your JsonResource toArray() method. Conditional when() and whenLoaded() fields automatically become optional TypeScript keys.
    link: /guide/enums-and-requests
    linkText: See Resources

  - icon: 🛡️
    title: Route Parameter Safety
    details: Inspects controller signatures to resolve Eloquent-bound route parameters. Auto-increments become number, UUIDs become string — fully compatible with Ziggy.
    link: /guide/routes-and-dx
    linkText: Type safe routes

  - icon: ✅
    title: CI / CD --check Flag
    details: Run php artisan typescript:generate --check in GitHub Actions to prevent stale types from merging. Exits with code 1 on any drift between generated and committed files.
    link: /guide/routes-and-dx
    linkText: Set up CI gates

  - icon: 👁️
    title: Watch Mode
    details: Polling watcher regenerates TypeScript files the moment any model, enum, form request or config changes. Pure PHP, cross-platform, zero native OS dependencies.
    link: /guide/routes-and-dx
    linkText: Enable watch mode

  - icon: 🧩
    title: Zod Schema Compilation
    details: Enable Zod output to emit runtime-validated schemas alongside your interfaces. Advanced constraints like email, min, and max are automatically mapped from Laravel rules.
    link: /guide/enums-and-requests
    linkText: Enable Zod
---
