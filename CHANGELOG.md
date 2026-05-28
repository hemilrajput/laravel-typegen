# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-05-28

### Added
- **Database Schema Fallbacks**: Automated column type and nullability inference via database schema inspection (`Schema::getColumns()`), falling back gracefully to fillables.
- **Eager-Loading TS Helpers**: Custom relationship wrapping using `Relation<T>` to help distinguish between unloaded, loaded, and null states in the frontend. Can be configured/disabled via `relations.wrap_with_relation`.

## [1.1.0] - 2026-05-28

### Added
- **Ignore Customization**: Supported excluding attributes and relations using `#[TypeScriptIgnore]` and the `ignore` array option on `#[TypeScript]`.
- **Pluggable Type Mappers**: Container-bound singleton registry on `CastTypeMapper` to programmatically register custom type mappers at runtime.
- **CLI Progress Bars**: Interactive terminal progress bar support for a cleaner generate command CLI experience.

## [1.0.0] - 2026-05-28

### Added
- **VitePress Documentation Site**: Built a fully-featured VitePress documentation site with comprehensive setup guides, Spatie migration comparison, and Inertia integrations.
- **CI/CD Quality Gates**: Integrated GitHub Actions workflows running Pest testing matrix, Pint style checking, and PHPStan static analysis on every push/PR.
- **Auto-Deployment Workflow**: Added GitHub Actions deployment for compiling and publishing VitePress docs to GitHub Pages.

## [0.4.0] - 2026-05-28

### Added
- **Route types generation**: New `typescript:routes` command to generate Ziggy-compatible typescript type mappings for named routes.
- **Watch mode**: Added `--watch` flag to `typescript:generate` utilizing a lightweight polling loop.
- **File splitting**: Configurable file splitting (`output.split`) to generate individual files for each type with auto-resolved relative imports and barrel `index.ts`.
- **Custom cast support**: Automatic mapping of custom Eloquent cast classes registered in config overrides.

## [0.3.0] - 2026-05-15

### Added
- Eloquent relationship support: opt in per-model via `#[TypeScript(includeRelations: [...])]`
- Auto-discovery of related models — referenced models are generated automatically
- Polymorphic `MorphTo` support via Laravel's morph map

## [0.2.0] - 2026-05-15

### Added
- **Enum Support**: `#[TypeScript]` on backed or pure enums generates TypeScript union types.
- **FormRequest Support**: `rules()` method auto-generates request DTO interfaces.
- **Enum-Cast Integration**: Models referencing enums via `$casts` produce typed references automatically.
- **Professional Setup**: Added Laravel Pint and Larastan for code quality.

## [0.1.0] - 2026-05-15

### Added
- Initial release with Eloquent model generation.
- `#[TypeScript]` attribute for opting into generation.
- Artisan `typescript:generate` command.

[1.2.0]: https://github.com/hemilrajput/laravel-typegen/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/hemilrajput/laravel-typegen/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/hemilrajput/laravel-typegen/compare/v0.4.0...v1.0.0
[0.4.0]: https://github.com/hemilrajput/laravel-typegen/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/hemilrajput/laravel-typegen/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/hemilrajput/laravel-typegen/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/hemilrajput/laravel-typegen/releases/tag/v0.1.0
