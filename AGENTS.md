# Made Cms Package

This repository is a Laravel package. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, publish tags, documentation, and examples aligned with `made-foryou/made-cms-package`.
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real. Actions, custom Eloquent Builders, Data objects and the site's strip types (`Content\Strips\StripType`) are the agreed extension points.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, commands, routes, published resources, and documentation promises.
- Every change a host or editor notices (public API, routes, config, migrations, commands, translations, screens) gets a line under `## [Unreleased]` in `CHANGELOG.md`, in the same change as the code. Use one heading per type in the order Added, Changed, Deprecated, Removed, Fixed, Security; a breaking change says what the host has to do. Internal refactors and tests stay out.

## Architecture Guidelines

- Work test-first: write the failing test in the mirrored `tests/` path, make it pass, then run `composer analyse` and `composer test`.
- `src/` is organised per bounded context (`Access`, `Content`). Technical layers (`Http`, `Console`, `Support`) live outside the contexts.
- A context contains `Actions`, `Builders`, `Data`, `Events`, `Models`, and when needed `Enums`, `Exceptions`, `Listeners`, `Notifications`, `Policies`. `Content` also holds `Strips` (the strip type definition API, with `Strips/Fields`). Create a folder with its first class, never empty.
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
- Editors build pages from strip types the site defines: one class per type in `app/Cms/Strips` (`made-cms-package.strips.path`/`namespace`) extending the abstract `StripType`, with a fixed `key()` that is stored with the content and never derived from the class name. `StripTypes` is the catalogue (a singleton bound in the service provider) and checks every definition when it is built; a broken definition is a developer error and throws.
- Strip definitions only describe: `StripType` and the immutable fields in `Content\Strips\Fields` (`final`, every setter returns a changed copy) derive validation rules, labels, defaults and normalised values, but never write, dispatch events or use facades. Actions validate and store strip content with them.
- A field has a stored form (`normalize()`) and a rendered form (`render()`, e.g. a link as its url). `StripType::render()` renders every field, fills a missing one from its default and puts `with()` on top of the rendered values.
- Visitors get strips only through `RenderStrips::run($page)` (`Content\Data\RenderedStrips`): published (the working copy in the edit mode), visible, in order, catalogue types only, as `{id, type, ...fields}`. Never hand `$page->strips` to a view or prop.
- Blade components for the site live in `Http\Components` under the component namespace `made-cms`. `<x-made-cms::strips>` renders `strips.{key}` or `view()` with `$strip` and throws `MissingStripView` before rendering when a view is missing.
- The site's strip views set `data-cms-strip` (required) and `data-cms-field`/`data-cms-items` (optional); the package only promises the id.
- A page holds its published strips in `cms_pages.strips` and a nullable draft in `draft_strips` (stored as `{id, type, visible, values}`, read through `Content\Data\PageStrips`). Every strip write goes to the draft through a Content Action; `PublishPage` validates the draft and makes it the published version. A strip whose type left the catalogue keeps its data.
- `CompareDraft` is the only place that says what a draft changes (`DraftChanges`) and whether it can be published or discarded: each strip counts once (added, removed, changed fields or visibility, moved), values as rendered (missing field = default), without key order or item ids.
- Every address of the site is a row in `cms_routes` (`Content\Models\Route`): a unique, normalised `path` (`Content\Data\PagePath`, `/` for home) and a polymorphic `routable` (pages as `cms_page`). New content with an address points its own row there; never store a path on the content table. A draft page keeps its address.
- The site serves pages with `Route::madeCms($responder)` (`Http\Routing\RouterMixin`): a nameless `{path?}` catch-all whose `ResolvePage` middleware runs `FindPublishedPage` and swaps the `path` parameter for the page. Visitors never see a draft.
- The edit mode is the site in the frame of the CMS: `?made-cms=edit` plus an editor who opens `Screen::Pages` (`CheckScreenAccess`). `ResolvePage` then finds the page with `FindPage` (any status), switches the scoped `Content\Data\EditMode` on (back off in `terminate()`, never in `handle()`, because `InjectLauncher` reads it afterwards) and sends `no-store`, `X-Frame-Options: SAMEORIGIN` and `noindex`. `RenderStrips` renders the working copy in it and `ShowLauncher` hides the launcher. Anyone else gets the visitor's answer: a draft stays 404, never 403.
- `?made-cms=preview` is the same mode outside the frame ("Voorvertoning" in a tab of its own); `EditMode` stays one flag, so frame-only behaviour belongs in `edit.js`, not in the mode.
- Pages form a tree (`parent_id`, `position` among siblings, `in_menu`). The address of a page is its parent's address plus its slug (`/` + slug at the top and under the home page). Only the Content Actions (`CreatePage`, `UpdatePage`, `MovePage`, `DeletePage`, `RestorePage`) change the tree, and they rewrite the addresses of the pages below in the same transaction. The home page (route `/`) keeps its address, stays at the top and is never deleted.
- Deleting a page is a soft delete that frees its `cms_routes` row; `made-cms:prune` (scheduled daily by the package) removes it after `pages.keep_deleted_days`.
- CMS screens with input use plain HTML forms (`@csrf`, `@method`), no JSON, except the edit shell (side panel and bar): a form post would reload the shell and the site in its frame. A refusal goes back to the form with `$request->reject()`; a success redirects through a Response class (`PageRedirect`) with a flash notice that `Shell` shows on the next page.
- The edit shell (`made-cms/pages/{page}/edit`, `EditPageResponse`, `cms/edit.blade.php`, `edit.js`) frames the page in the edit mode. `edit.js` outlines every `[data-cms-strip]` from a shadow root of its own element in the frame, keeps the chosen strip across reloads and blocks links and forms there; the only change it makes to the site is patching `[data-cms-field]` elements. Strip names and texts reach it as JSON (`data-config`) from `ListEditableStrips` and `lang/`.
- Adding a context: add it to `contexts()` in `tests/Pest.php` and start with one Model and one Action, both tested.
- Arch tests in `tests/Arch/` enforce these rules; a failing arch test means the code is wrong, not the test.
- Decisions are business rules too, even without a write (e.g. who sees the launcher): put them in a context Action with a Data object as input. Http only translates the request into that Data.
- Controllers stay as empty as possible: a FormRequest in `src/Http/Requests/<Context>/` (final, suffix `Request`, extends `LauncherRequest`) validates, holds the translated messages and turns the input into context Data with `toData()`; the controller runs one Action and returns a Response class from `src/Http/Responses/`. A rejection by an Action becomes a field error through `$request->reject($field, $error)`; controllers never validate or build messages themselves.
- The side panel (`panel.js`) builds its controls from `StripType::describe()` (`DescribeStrip`, `GET made-cms/pages/{page}/strips/{strip}`) and saves with fetch + JSON (`X-CSRF-TOKEN` from `data-config`): always every field to `UpdateStrip`, visibility to `SetStripVisibility`. A refusal is the 422 of the Action's `ValidationException`, keyed by field path. Fixed texts live in the `<template>`s of `cms/edit.blade.php`, the texts it fills in in `cms.edit.panel_texts`; `edit.js` and `panel.js` talk through `made-cms:select`, `made-cms:input`, `made-cms:saved` and `made-cms:visibility` events on `[data-edit-shell]`.
- Watching along: `panel.js` sends every change as `made-cms:input` `{id, path, type, value, count, seq}` with the value as the strip renders it (`rendered()`, like `Field::render()`); `edit.js` patches it into the `[data-cms-field]` elements (in `[data-cms-items]` the direct children are the items, a field in it is relative to the item). What it cannot patch (no element, empty value, choice, a list changing, visibility) marks the strip stale up to that `seq`, and the `made-cms:saved {id, seq}` that stores it reloads the frame. Unsaved changes are patched again after every reload.
- A patch changes only the single text node (`nodeValue`), `href` or the HTML of rich text, never `textContent` or the element itself, so React and Vue keep it; an element that holds more than its text is not patched. A long text follows the form the site prints it in (`<br>` or line breaks in the text).
- A click on an outline opens the field under the pointer (`elementsFromPoint`) through `made-cms:select {id, field, count}`; the tab with the name and the keyboard choose the strip only.
- A new field type adds its `type()` and `details()` for `describe()`, a `<template data-field="…">` in `cms/edit.blade.php` and its control in `panel.js`.
- The bar (`bar.js`) shows the state of `GET …/pages/{page}/draft` (`DraftResponse`, texts and plurals made on the server), publishes with `POST …/publish` and discards with `DELETE …/draft`; both answer 204 and the bar asks for the state again. Its slots are named `bar-*`, because the panel takes the first `[data-slot]` of the shell.
- Before publishing the bar waits for the panel to save what it holds (`made-cms:flush`), before discarding for it to stop saving (`made-cms:discarding`); after publishing the frame reloads (`made-cms:published`), after discarding the whole shell. A strip that stops the publication is chosen with its errors (`made-cms:choose` → `made-cms:select`).
- "Ongedaan maken" is session state of the panel: each strip keeps what the server stored before every save and a step back saves it again through `UpdateStrip`/`SetStripVisibility`; there is no undo on the server.
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
- Pest tests: `composer test:unit` (without the browser tests)
- Browser tests: `composer test:browser` (once: `npm ci && npx playwright install chromium`)
- Workbench build: `composer build`
- Workbench server: `composer serve`

## Local Skills

- `package-architecture`: use when adding Actions, Models, Builders, Data, Events, Enums or Exceptions to a context, or when adding a new context.
- `package-scaffold`: use when adding package capabilities or wiring them through the service provider, including commands, migrations, routes, config, views, translations, assets, middleware, publish tags, workbench files, and console-only behavior.
- `package-testing`: use when adding or changing package tests with Pest 4/5 and Orchestra Testbench.
- `package-release`: use when preparing changelog, release notes, tags, or GitHub release workflow changes.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the PHP and Laravel support matrix.
- `package-generate-skill`: use when updating the bundled Boost skill from the package implementation, README, and examples.
