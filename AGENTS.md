# Made Cms Package

This repository is a Laravel package. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, publish tags, documentation, and examples aligned with `made-foryou/made-cms-package`.
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real. Actions, custom Eloquent Builders and Data objects are the agreed extension points.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, commands, routes, published resources, and documentation promises.

## Architecture Guidelines

- Work test-first: write the failing test in the mirrored `tests/` path, make it pass, then run `composer analyse` and `composer test`.
- `src/` is organised per bounded context (`Access`, `Content`). Technical layers (`Http`, `Console`, `Support`) live outside the contexts.
- A context contains `Actions`, `Builders`, `Data`, `Events`, `Models`, and when needed `Enums`, `Exceptions`, `Listeners`, `Notifications`, `Policies`. Create a folder with its first class, never empty.
- Contexts never import each other and never use `Http`, `Console`, or the service provider. Communicate across contexts through events.
- Actions are the only place for writes and business rules: `final`, `use AsAction`, a typed `handle()`, invoked as `Action::run(...)`. Do not use `asController`, `asListener`, `asJob`, `asCommand` or `rules()`.
- Models only represent a table row: `$table`, `$fillable`, `casts()`, relations, `newEloquentBuilder()` and `newFactory()`. No business logic, no facades.
- Reads go through custom Eloquent Builders (`Page::query()->published()`), one per model, `final`, suffix `Builder`. No repositories, no ad-hoc queries in Actions or controllers.
- Data objects are `final readonly` plain PHP classes. Events are `final` and `Dispatchable`. Enums are string-backed. Exceptions are `final`.
- Code identifiers are English. UI text is Dutch in `lang/nl` with an English fallback in `lang/en`. Tables use the `cms_` prefix.
- Migrations live in `database/migrations` (`create_cms_*_table`), factories in `database/factories/<Context>/`.
- Do not use Eloquent class attributes such as `#[Table]` or `#[UseEloquentBuilder]`; Laravel 12.0 is still supported.
- Hosts own users; the package never defines its own user model.
- Adding a context: add it to `contexts()` in `tests/Pest.php` and start with one Model and one Action, both tested.
- Arch tests in `tests/Arch/` enforce these rules; a failing arch test means the code is wrong, not the test.

## Quick Commands

- Full validation: `composer test`
- Formatting check: `composer lint:check`
- Static analysis: `composer analyse`
- Pest tests: `composer test:unit`
- Workbench build: `composer build`
- Workbench server: `composer serve`

## Local Skills

- `package-architecture`: use when adding Actions, Models, Builders, Data, Events, Enums or Exceptions to a context, or when adding a new context.
- `package-scaffold`: use when adding package capabilities or wiring them through the service provider, including commands, migrations, routes, config, views, translations, assets, middleware, publish tags, workbench files, and console-only behavior.
- `package-testing`: use when adding or changing package tests with Pest 4/5 and Orchestra Testbench.
- `package-release`: use when preparing changelog, release notes, tags, or GitHub release workflow changes.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the PHP and Laravel support matrix.
- `package-generate-skill`: use when updating the bundled Boost skill from the package implementation, README, and examples.
