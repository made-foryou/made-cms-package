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
- Feature tests get `RefreshDatabase` through `tests/Pest.php`. The database is SQLite in memory, or the MySQL or PostgreSQL server `DB_CONNECTION` names (`DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`): `tests/Fixtures/Database/TestDatabase` creates `made_cms_package_test` there (`_N` per parallel worker) in `TestCase::defineDatabaseMigrations()` and never reads `DB_DATABASE` or `DB_URL`. `TestCase` only registers the package migrations with the migrator; `RefreshDatabase` runs them once per database. Unit tests have no tables.
- Write every test for all three databases: compare JSON read back from the database with `toBeStoredAs()` (MySQL sorts the keys of an object), match the text of a query with `quoted('cms_media')`, use `missingId()` for a row that is not there (MySQL and PostgreSQL keep counting ids after a rollback), and order a list read from the database. A test that only holds on SQLite is changed, never skipped.
- The default test locale is `nl` with `en` as fallback.
- Create fixtures with model factories, never assume ids, and use `freezeSecond()` when comparing stored timestamps (the database drops microseconds).
- Arch tests live in `tests/Arch/`, one file per concern. They run without booting the app. Guard every rule with `contextNamespaces()` so a layer is only checked once it has classes.
- A new context must be added to `contexts()` in `tests/Pest.php`, otherwise the arch tests ignore it.

## Browser Tests

- Use a browser test when the behaviour lives in JavaScript (the launcher, the edit shell, the scan); use a feature test when the server answer is the behaviour.
- They live in `tests/Browser/`, form the testsuite `Browser` and run only with `composer test:browser`; `composer test` and `composer test:unit` skip them with `--exclude-testsuite=Browser`. Never filter them out with a group: the plugin starts Playwright as soon as a browser test file is loaded, so a run without Node fails. One-time setup: `npm ci && npx playwright install chromium`.
- `pestphp/pest-plugin-browser` drives Chromium through Playwright against the app in the test process, so factories, Actions, `RefreshDatabase` and `actingAs($editor, 'made-cms')` work as in feature tests.
- Start with `useWorkbenchSite()` (workbench strip types, views and `Route::madeCms()`) and create the page you visit, e.g. `Page::factory()->published()->at('/')`.
- The launcher only shows to allowed addresses: `config(['made-cms-package.launcher.allowed_ips' => ['127.0.0.1', '::1']])`.
- A first visit opens the login panel by itself; set `localStorage` key `made-cms-launcher-intro` and `refresh()` when the test opens it by hand.
- CSS selectors reach into the launcher's open shadow root: `click('.beheer')`, `type('#made-cms-email', ...)`. Prefer data attributes from the markup (`[data-methode="password"]`, `form[data-form="email"] button[type="submit"]`) over copy, and take copy from `lang/` with `__()`.
- Clicks wait for their element, assertions do not: after an animation wait with `expect($page->script(waitUntil('<js condition>')))->toBeTrue()` before asserting.
- End every browser test with `assertNoJavaScriptErrors()`.
- Prove a new browser test fails without the behaviour (wrong password, no login) before trusting a first green run. A failure saves a screenshot in `tests/Browser/Screenshots/` (ignored by git, uploaded by CI).

## References

- `tests/TestCase.php`
- `tests/Pest.php`
- `tests/Feature/`
- `tests/Unit/`
- `tests/Arch/`
- `tests/Browser/`
- `composer.json` scripts

## Examples

- Test config merge and config override by asserting default package config, then overriding the host config value in the Testbench app.
- Test publishable assets, migrations, views, lang files, or config by invoking vendor publish behavior and asserting the target path exists.
- Test routes with Testbench HTTP requests, commands with Artisan assertions, migrations by running their `up()` and `down()` on the test database (on MySQL that commits, and `RefreshDatabase` migrates again for the next test), and workbench behavior after `composer build` when needed.

## Anti-Patterns

- Deleting real package tests because they are inconvenient.
- Relying only on smoke tests when behavior needs assertions.
- Testing implementation details when observable package behavior is available.
- Keeping throwaway scaffolding experiment tests in the package test suite.
