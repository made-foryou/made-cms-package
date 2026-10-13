---
name: package-testing
description: "Use this skill when writing, editing, fixing, or reviewing package tests with Pest 4/5 and Orchestra Testbench, including TDD, feature tests, unit tests, type coverage, arch tests, workbench behavior, commands, routes, config, migrations, and publishable resources."
license: MIT
metadata:
  author: laravel
---

# Package Testing

## Primary Goal

Prove package behavior with Pest 4/5, Orchestra Testbench, and the local `tests/TestCase.php` setup before or alongside implementation.

## Workflow

1. Start with TDD: write the smallest failing package test for the requested behavior, then implement the smallest change that makes it pass.
2. Cover happy-path, unhappy-path, and edge-case behavior when the feature has meaningful failure modes.
3. Prefer focused feature tests for package integration behavior and arch tests for broad constraints.
4. Use `composer test:unit -- --filter ...` while iterating, `composer test:types` when type-sensitive tests or code changed, and `composer test` before finishing.
5. Keep real package tests in the suite and remove only throwaway tests that were explicitly created for local scaffolding experiments.

## Test Layout

- Test paths mirror `src/`: `tests/Feature/<Context>/<Layer>/<Class>Test.php` and `tests/Unit/<Context>/<Layer>/<Class>Test.php`.
- Feature tests get `RefreshDatabase` through `tests/Pest.php` on an in-memory SQLite database; the package migrations are loaded in `TestCase::defineDatabaseMigrations()`.
- The default test locale is `nl` with `en` as fallback.
- Create fixtures with model factories, never assume ids, and use `freezeSecond()` when comparing stored timestamps (the database drops microseconds).
- Arch tests live in `tests/Arch/`, one file per concern. They run without booting the app. Guard every rule with `contextNamespaces()` so a layer is only checked once it has classes.
- A new context must be added to `contexts()` in `tests/Pest.php`, otherwise the arch tests ignore it.

## References

- `tests/TestCase.php`
- `tests/Pest.php`
- `tests/Feature/`
- `tests/Unit/`
- `tests/Arch/`
- `composer.json` scripts

## Examples

- Test config merge and config override by asserting default package config, then overriding the host config value in the Testbench app.
- Test publishable assets, migrations, views, lang files, or config by invoking vendor publish behavior and asserting the target path exists.
- Test routes with Testbench HTTP requests, commands with Artisan assertions, migrations with a SQLite test database, and workbench behavior after `composer build` when needed.

## Anti-Patterns

- Deleting real package tests because they are inconvenient.
- Relying only on smoke tests when behavior needs assertions.
- Testing implementation details when observable package behavior is available.
- Keeping throwaway scaffolding experiment tests in the package test suite.
