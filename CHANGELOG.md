# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - 2026-05-30

### Breaking Changes
- **PSR-4 Namespace Refactor**: The root namespace has been changed from `hemilrajput\TypeGen` to `Hemilrajput\TypeGen` to fully comply with PSR-4 standards and standard PHP conventions. You will need to update all imports (e.g., `use Hemilrajput\TypeGen\Attributes\TypeScript;`).

---

## [2.0.0] - 2026-05-30

### Breaking Changes
- **Dropped PHP 8.2 support** — minimum requirement is now PHP 8.3.

### Added
- **VS Code Extension** (`vscode-extension/`): Auto-runs `php artisan typescript:generate` when a PHP file containing `#[TypeScript]` is saved. Includes a status-bar toggle, manual trigger via Command Palette, and a streaming output channel.
- **TS Utility Package** (`@hemilrajput/laravel-typegen-helpers`): New npm package with `PaginatedResponse<T>`, `SimplePaginatedResponse<T>`, `InertiaForm<T>`, `ApiResource<T>`, `ApiResourceCollection<T>`, `Relation<T>`, and utility types (`DeepPartial`, `RequireFields`, `Unarray`, `EnumRecord`).
- **Module-Wise Architecture**: Enabling `split` mode now generates a beautifully organized module-wise directory structure (`Models/`, `Enums/`, `Requests/`) with automated relative imports, rather than flattening files into a single directory.

### Changed
- CI test matrix now runs on PHP 8.3/8.4 × Laravel 11/12/13.
- `orchestra/testbench` dev dependency supports `^9.0|^10.0`.

### Fixed
- **Un-typehinted Relationships**: Safely dynamically evaluates and resolves relationship types (using `Relation::noConstraints()`) for Eloquent models where developers omitted explicit PHP return types (e.g. `public function posts()`).

---

## [1.3.0] - 2026-05-31

### Added
- **Pre/Post Generation Hooks**: Added support for running shell commands before or after type generation (e.g. running Prettier or linters), replacing the `{file}` placeholder with the output file/directory.
- **JsonResource Transformer**: Added type generation for Laravel API `JsonResource` responses using class-level PHPDoc `@property` definitions, with automatic fallback mapping to Eloquent model schemas.

## [1.2.0] - 2026-05-28

### Added
- **Database Schema Fallbacks**: Automated column type and nullability inference via database schema inspection (`Schema::getColumns()`), falling back gracefully to fillables.
- **Eager-Loading TS Helpers**: Custom relationship wrapping using `Relation<T>` to help distinguish between unloaded, loaded, and null states in the frontend. Can be configured/disabled via `relations.wrap_with_relation`.

## [1.1.0] - 2026-05-27

### Added
- **Ignore Customization**: Supported excluding attributes and relations using `#[TypeScriptIgnore]` and the `ignore` array option on `#[TypeScript]`.
- **Pluggable Type Mappers**: Container-bound singleton registry on `CastTypeMapper` to programmatically register custom type mappers at runtime.
- **CLI Progress Bars**: Interactive terminal progress bar support for a cleaner generate command CLI experience.

## [1.0.0] - 2026-05-25

### Added
- **VitePress Documentation Site**: Built a fully-featured VitePress documentation site with comprehensive setup guides, Spatie migration comparison, and Inertia integrations.
- **CI/CD Quality Gates**: Integrated GitHub Actions workflows running Pest testing matrix, Pint style checking, and PHPStan static analysis on every push/PR.
- **Auto-Deployment Workflow**: Added GitHub Actions deployment for compiling and publishing VitePress docs to GitHub Pages.

## [0.4.0] - 2026-05-22

### Added
- **Route types generation**: New `typescript:routes` command to generate Ziggy-compatible typescript type mappings for named routes.
- **Watch mode**: Added `--watch` flag to `typescript:generate` utilizing a lightweight polling loop.
- **File splitting**: Configurable file splitting (`output.split`) to generate individual files for each type with auto-resolved relative imports and barrel `index.ts`.
- **Custom cast support**: Automatic mapping of custom Eloquent cast classes registered in config overrides.

## [0.3.0] - 2026-05-19

### Added
- Eloquent relationship support: opt in per-model via `#[TypeScript(includeRelations: [...])]`
- Auto-discovery of related models — referenced models are generated automatically
- Polymorphic `MorphTo` support via Laravel's morph map

## [0.2.0] - 2026-05-16

### Added
- **Enum Support**: `#[TypeScript]` on backed or pure enums generates TypeScript union types.
- **FormRequest Support**: `rules()` method auto-generates request DTO interfaces.
- **Enum-Cast Integration**: Models referencing enums via `$casts` produce typed references automatically.
- **Professional Setup**: Added Laravel Pint and Larastan for code quality.

## [0.1.0] - 2026-05-13

### Added
- Initial release with Eloquent model generation.
- `#[TypeScript]` attribute for opting into generation.
- Artisan `typescript:generate` command.

[2.0.0]: https://github.com/hemilrajput/laravel-typegen/compare/v1.3.0...v2.0.0
[1.3.0]: https://github.com/hemilrajput/laravel-typegen/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/hemilrajput/laravel-typegen/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/hemilrajput/laravel-typegen/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/hemilrajput/laravel-typegen/compare/v0.4.0...v1.0.0
[0.4.0]: https://github.com/hemilrajput/laravel-typegen/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/hemilrajput/laravel-typegen/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/hemilrajput/laravel-typegen/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/hemilrajput/laravel-typegen/releases/tag/v0.1.0
