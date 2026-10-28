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
- `Support` holds final, context-free helpers (site name and url, launcher locale, QR code) that contexts and Http may both use; it never uses a context, `Http` or `Console`.
- Actions are the only place for writes and business rules: `final`, `use AsAction`, a typed `handle()`, invoked as `Action::run(...)`. Do not use `asController`, `asListener`, `asJob`, `asCommand` or `rules()`.
- Models only represent a table row: `$table`, `$fillable`, `$hidden`, `casts()`, relations, `newEloquentBuilder()` and `newFactory()`. No business logic, no facades. `Editor` also implements Laravel's `Authenticatable` contract through its trait, so the guard can log it in.
- Reads go through custom Eloquent Builders (`Page::query()->published()`), one per model, `final`, suffix `Builder`. No repositories, no ad-hoc queries in Actions or controllers.
- Data objects are `final readonly` plain PHP classes. Events are `final` and `Dispatchable`. Enums are string-backed. Exceptions are `final`.
- Code identifiers are English. UI text is Dutch in `lang/nl` with an English fallback in `lang/en`. Tables use the `cms_` prefix.
- Migrations live in `database/migrations` (`create_cms_*_table`), factories in `database/factories/<Context>/`.
- Do not use Eloquent class attributes such as `#[Table]` or `#[UseEloquentBuilder]`; Laravel 12.0 is still supported.
- The CMS has its own editors (`cms_editors`, `Access\Models\Editor`) and its own session guard (`made-cms-package.auth.guard`, default `made-cms`), registered next to the host's guards. It runs beside the host site and never reads or changes the host's users table, guards or login.
- Editors hold one or more fixed roles (`Access\Enums\Role`, stored in `cms_editors.roles`); `Admin` opens every screen, also screens added later.
- Which role opens which screen lives only in `Role::screens()`. A new CMS screen is a `Screen` case, granted there, with its `ScreenGroup` in `Screen::group()`; the screens of a group follow each other in the cases.
- Every screen has a route `made-cms/<screen>` with `EnsureScreenAccess::using(Screen::X)`. Until it is built, the loop in the routes file sends it to `UpcomingScreenController`; a built screen leaves the loop and gets its own controller.
- Menus and dashboards list `ListScreens::run($editor)`, grouped by `Screen::group()` (`Http\Cms\Menu`); hiding a menu item is UI only, the route middleware is the authorisation.
- The start page (`made-cms/start`) opens for every logged-in editor (`EnsureEditor`). Its blocks are static design samples in `Http\Cms\StartPageSample` until the contexts deliver them; a sample item never links to a screen the editor does not open.
- Roles change only through `AssignRoles`: the editor making the change needs the `Editors` screen, only an admin gives or takes `Admin`, the last admin keeps it, and every editor has at least one role.
- `Content` never checks roles; screen access is enforced at the Http boundary.
- Adding a context: add it to `contexts()` in `tests/Pest.php` and start with one Model and one Action, both tested.
- Arch tests in `tests/Arch/` enforce these rules; a failing arch test means the code is wrong, not the test.
- Decisions are business rules too, even without a write (e.g. who sees the launcher): put them in a context Action with a Data object as input. Http only translates the request into that Data.
- Controllers stay as empty as possible: a FormRequest in `src/Http/Requests/<Context>/` (final, suffix `Request`, extends `LauncherRequest`) validates, holds the translated messages and turns the input into context Data with `toData()`; the controller runs one Action and returns a Response class from `src/Http/Responses/`. A rejection by an Action becomes a field error through `$request->reject($field, $error)`; controllers never validate or build messages themselves.
- Http middleware the host must get without configuration is appended to the kernel's `web` group via `callAfterResolving(Kernel)`, never pushed onto the router (that is lost when the kernel re-syncs its groups).
- The launcher (Blade markup under the `made-cms-package-launcher` view namespace, css, js, fonts) lives in `resources/launcher/`, is never published, and its static assets are served by a whitelisted controller in a route group without middleware; hosts never publish them.
- CMS pages (start page, screens) render in the shell `resources/launcher/cms/` (`Http\Cms\Shell`, one Response class per page) with `cms.css` and `cms.js`, served by the same whitelisted controller. They are plain documents without a shadow root, follow `prefers-color-scheme`, and are sent `no-store`, `DENY` and `noindex`.
- The launcher's visibility is UI only and never authorisation.
- Login endpoints answer `{step, data, csrf_token}` (`StepResponse`); the server decides the next step and the JSON carries data only. All copy lives in `lang/` and the launcher's step templates, never in JS or JSON.
- Never reveal whether an address has an account: same answer, same message and (with `Timebox`) same time for unknown addresses; rate limits key on what was typed, never on the account.
- Secrets (login codes, link tokens, TOTP secrets) are stored hashed or encrypted and only travel in memory; events and notifications that carry them are handled synchronously and never queued.

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
