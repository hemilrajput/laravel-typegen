# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.2.3] - 2026-07-29

### Added
- **API Resource Support via AST Parsing**: Resources are no longer limited to PHPDoc tags. TypeGen now leverages `nikic/php-parser` to statically analyze the AST of the `toArray()` method, generating highly accurate TypeScript types. Also seamlessly supports conditional fields via `when()` and `whenLoaded()`.
- **CI Safety Checks**: Added `--check` flag to `typescript:generate`. Designed for CI/CD pipelines, this flag fails the build if the generated types do not exactly match the files checked into version control.
- **Zod Advanced Constraints**: Zod schemas now extract constraints from Laravel validation rules such as `email`, `min:x`, and `max:x` producing `.email()`, `.min(x)`, and `.max(x)` respectively.

### Changed
- **Modern Accessor Type Inference**: Supported reflection-based type inference for modern Laravel accessors (`Attribute::make()`). TypeGen evaluates the return type of the underlying closure to avoid a generic `any` output.
- **Custom Cast Auto-Inference**: Custom cast classes implementing `CastsAttributes` are now automatically inferred by inspecting the return type of their `get()` method. Native casts like `AsCollection` and `AsStringable` are also automatically mapped.
- **Strict Route Parameter Typing**: Route parameters bound to Eloquent models are now intelligently mapped by resolving the bound model and inspecting its `getKeyType()` (integer IDs become `number`, UUIDs become `string`).

## [2.2.2] - 2026-07-15

### Added
- **Zod Schema Compilation**: Automatically generate matching Zod validation schemas for Laravel FormRequests to validate data on the client side before submission.
- **Inertia Recipe**: Added comprehensive documentation for Inertia.js integration.

### Fixed
- Fixed an issue where `readonly` modifiers were incorrectly placed after the property name causing TypeScript compilation errors (TS1354).
- Fixed Laravel 11 backwards compatibility with `app()->getNamespace()`.
- Fixed missing `configure-pages` step in GitHub Actions preventing proper documentation deployment.

## [2.2.1] - 2026-07-12

### Fixed
- Fixed a Packagist synchronization issue (Upstream re-tag blocked) caused by caching of the `v2.2.0` tag.
- Fixed strict type hinting for `RuleToTypeMapper` to satisfy Rector CI requirements.
- Fixed code style formatting via Laravel Pint.

## [2.2.0] - 2026-07-12

### Security
- Fixed a Medium-severity Path Traversal vulnerability where manipulated output path configurations could write or delete files outside the project root. (SEC-001)
- Fixed a High-severity Command Injection vulnerability where output paths containing spaces or shell meta-characters passed to pre/post generation hooks were not escaped properly. (SEC-002)

### Changed
- **FormRequest Nullable arrays**: Fixed a critical logic bug where nested objects and arrays of objects ignored `nullable: true` rules, strictly outputting them as required and non-null. The generated types now properly suffix `| null` and apply optional parameters (`?`) where appropriate. (BUG-003)
- **Model Accessor Type Inference**: Added support for parsing native PHP return types on custom model accessors (e.g. `public function getIsAdminAttribute(): bool`). Previously, un-casted appended attributes blindly defaulted to `any`. The generator now maps `array` to `any[]` and `object` to `Record<string, any>`. (BUG-004)

## [2.1.1] - 2026-06-20

### Fixed
- Fixed Rector configuration to maintain Laravel 11 compatibility by limiting Laravel sets to `UP_TO_LARAVEL_110`.
- Aligned VS Code extension version to match main package release version.

### Added
- Integrated Rector validation checks into GitHub Actions CI pipeline.
- Documented comprehensive pre-commit/pre-push validation commands in contributing guidelines.

---

## [2.1.0] - 2026-06-14

### Changed
- **Strict Schema Enforcement**: The generator now throws a `RuntimeException` instead of silently falling back to `$fillable` if a model's database table does not exist. This prevents the silent emission of wrongly typed interfaces.
- **Cast Nullability**: The generator correctly preserves database schema nullability when processing casts.
- **Appended Attributes**: The generator dynamically parses `$appends` and emits appended properties.

### Fixed
- Fixed an issue where the CI testing matrix would not test compilation correctly due to the npm `tsc` package.

---

## [2.0.2] - 2026-06-14

### Changed
- Maintained wide range support for `php: ^8.2|^8.3|^8.4` and `illuminate/*: ^11.0|^12.0|^13.0`.
- Expanded CI testing matrix to ensure all permutations of PHP 8.2-8.4 and Laravel 11-13 pass.

---

## [2.0.0] - 2026-05-30

### Breaking Changes
- **PSR-4 Namespace Refactor**: The root namespace has been changed from `hemilrajput\TypeGen` to `Hemilrajput\TypeGen` to fully comply with PSR-4 standards and standard PHP conventions. You will need to update all imports (e.g., `use Hemilrajput\TypeGen\Attributes\TypeScript;`).
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

## [1.3.0] - 2026-05-29

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

[2.2.3]: https://github.com/hemilrajput/laravel-typegen/compare/v2.2.2...v2.2.3
[2.2.2]: https://github.com/hemilrajput/laravel-typegen/compare/v2.2.1...v2.2.2
[2.2.1]: https://github.com/hemilrajput/laravel-typegen/compare/v2.2.0...v2.2.1
[2.2.0]: https://github.com/hemilrajput/laravel-typegen/compare/v2.1.1...v2.2.0
[2.1.1]: https://github.com/hemilrajput/laravel-typegen/compare/v2.1.0...v2.1.1
[2.1.0]: https://github.com/hemilrajput/laravel-typegen/compare/v2.0.2...v2.1.0
[2.0.2]: https://github.com/hemilrajput/laravel-typegen/compare/v2.0.1...v2.0.2
[2.0.1]: https://github.com/hemilrajput/laravel-typegen/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/hemilrajput/laravel-typegen/compare/v1.3.0...v2.0.0
[1.3.0]: https://github.com/hemilrajput/laravel-typegen/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/hemilrajput/laravel-typegen/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/hemilrajput/laravel-typegen/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/hemilrajput/laravel-typegen/compare/v0.4.0...v1.0.0
[0.4.0]: https://github.com/hemilrajput/laravel-typegen/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/hemilrajput/laravel-typegen/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/hemilrajput/laravel-typegen/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/hemilrajput/laravel-typegen/releases/tag/v0.1.0
