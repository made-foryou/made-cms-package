---
name: made-cms-package-development
description: >
  Configure and apply the Made Cms Package package in Laravel applications.
license: MIT
metadata:
  author: Made
---

# Made Cms Package

Use this skill when a Laravel application needs to integrate the Made Cms Package package.

## Primary Goal

- apply the `made-foryou/made-cms-package` package's public API in the smallest correct way

## Workflow

### 1. Inspect the Laravel app context

- confirm the app is a Laravel 12 or 13 project on PHP 8.4+
- inspect the target code paths where the package should be applied

### 2. Install

- `composer require made-foryou/made-cms-package`; the service provider is auto-discovered
- publish and run the migrations: `php artisan vendor:publish --tag="made-cms-package-migrations"` and `php artisan migrate`

### 3. Launcher (the MADE tile on the website)

- the tile and its login panel appear automatically on every full HTML page of the `web` middleware group; do not add middleware, views, assets or Blade directives to the host
- it is only shown to remembered editors and to IP addresses in `MADE_CMS_LAUNCHER_IPS` (comma separated, CIDR allowed); set it in `.env`, not in code
- switch it off with `MADE_CMS_LAUNCHER_ENABLED=false` (for example in `phpunit.xml`) or `config(['made-cms-package.launcher.enabled' => false])`
- exclude extra paths through `launcher.except` in the published config (`php artisan vendor:publish --tag="made-cms-package-config"`)
- behind a proxy, configure `trustProxies` in `bootstrap/app.php`, otherwise the IP allowlist sees the proxy address
- the launcher markup, styles and script are not publishable: they ship and update together with the package

### 4. Editors and logging in

- editors are CMS accounts of their own (`cms_editors`), separate from the app's users; never point them at `App\Models\User`
- create one with `php artisan made-cms:editor <email> --name="..." --role=admin` (empty password = code login only; leave `--role` out to pick roles from a list), or `CreateEditor::run(new NewEditor(email: ..., name: ..., password: null, roles: [Role::Admin]))` in a seeder
- editors log in through the panel the tile opens, or on the login page at `/made-cms` (the `routes.prefix`; link to it from invitation mails with `route('made-cms-package.login')`), both through the package's own `made-cms` session guard; it never logs anyone in to the app; check a CMS login with `auth('made-cms')->check()`
- set `APP_URL` and `MAIL_FROM_ADDRESS`: the login mail is sent right after the response through the app's mailer, never queued, and its link is built from `APP_URL`
- use a shared, persistent cache store in production: the login rate limiters live there
- name a page in the edit bar with `<meta name="made-cms:page" content="...">`; a fixed header can offset itself with the `--made-cms-bar-height` css variable

### 5. Roles and screens

- every editor holds at least one fixed role (`Made\CmsPackage\Access\Enums\Role`): `admin` (every screen), `webmaster`, `content-manager`, `seo-specialist`, `owner`; the roles decide which CMS screens (`Screen` enum) the editor opens
- change roles with `AssignRoles::run(new RoleAssignment(by: $admin, editor: $editor, roles: [...]))`; it throws `RolesRejected` when the editor making the change may not (only admins and webmasters change roles, only an admin gives or takes `admin`, the last admin keeps it)
- read access with `ListScreens::run($editor)` or `CheckScreenAccess::run(new ScreenVisit($editor, Screen::Redirects))`, and find editors with `Editor::query()->withRole(Role::Admin)`
- the package registers a route per screen at `/made-cms/<screen>` (`route('made-cms-package.screens.redirects')`), guarded by `EnsureScreenAccess`; until a screen is built it shows that it is coming
- guard a route of your own for a CMS screen with `->middleware(EnsureScreenAccess::using(Screen::Redirects))`
- the CMS start page is `/made-cms/start` (`route('made-cms-package.start')`): every logged-in editor opens it, with a menu of the screens of their roles; the edit bar and the login page link to it, so the host adds nothing

### 6. Strip types

- editors build pages from the site's strip types: one class per type in `app/Cms/Strips` that extends `Made\CmsPackage\Content\Strips\StripType` (config `strips.path` and `strips.namespace`); the package finds them on its own
- implement `key()` (fixed, lowercase letters, digits and dashes, e.g. `hero`) and `fields()`; optionally `label()`, `description()`, `view()` (the Blade view, default `strips.{key}`) and `with(array $values)` (extra data such as the latest news; it receives the rendered values and its keys land next to the fields)
- field types in `Made\CmsPackage\Content\Strips\Fields`: `Text`, `LongText`, `RichText` (cleaned HTML: p, strong, em, ul, ol, li, a), `Link` (`{"page": id}` or `{"url": "…"}`), `Button` (text + link), `Choice` (list, `value => label` or a backed enum) and `Items` (a list of items with the same fields, `min()`/`max()`); every field takes `label()`, `hint()`, `required()` and `default()`
- the field names `id`, `type` and `visible` are reserved; a broken definition throws `InvalidStripType` or `DuplicateStripType` as soon as the catalogue (`app(StripTypes::class)`) is built

### 7. Use the actions

- a page holds its published strips (`strips`) and a draft (`draft_strips`, `null` without one); every strip has an `id`, a `type` key, `visible` and `values`
- `AddStrip::run($page, 'hero', $position)`, `MoveStrip::run($page, $stripId, $position)`, `RemoveStrip::run($page, $stripId)`, `UpdateStrip::run($page, $stripId, $values)` (throws a `ValidationException` keyed by field) and `SetStripVisibility::run($page, $stripId, $visible)` change the draft; visitors keep the published strips
- `PublishPage::run($page)` makes the draft the published version and refuses a draft with an invalid strip (errors keyed `strips.<id>.<field>`); `DiscardDraft::run($page)` goes back to the published version; query pages with `Page::query()->published()`
- a strip whose type is no longer in the catalogue keeps its data; only `UpdateStrip` refuses it
- listen to `EditorLoggedIn`, `EditorLoggedOut`, `EditorRolesChanged`, `TwoFactorEnabled`, `StripAdded`, `StripMoved`, `StripRemoved`, `StripUpdated`, `StripVisibilityChanged`, `DraftDiscarded` and `PagePublished` for side effects

### 8. Serve the pages

- serve the CMS pages with one line as the **last** line of `routes/web.php`: `Route::madeCms(fn (Page $page) => view('page', ['page' => $page]))` (Blade), `Route::madeCms(fn (Page $page) => Inertia::render('Page', ['title' => $page->title, 'strips' => RenderStrips::run($page)->toArray()]))` (Inertia), or `Route::madeCms(ShowPage::class)` with an invokable class
- the macro catches every `GET` address no route above it claims, looks up the published page there and hands it to the responder; no page or a page that is not published answers 404; routes of the site above it win
- it takes the prefix and middleware of a surrounding group, returns the route without a name (chain `->name()` or `->middleware()`), can be used more than once and works with `route:cache`
- the address of a page is `$page->route->path` (`/` for home, `/over-ons/team` nested), stored in `cms_routes`; look up what a visitor sees with `FindPublishedPage::run(PagePath::fromString($path))`

### 9. Render the strips

- `RenderStrips::run($page)` gives the strips a visitor sees: published, in order, without hidden strips or strips of a type that left the catalogue; `->toArray()` is `[{id, type, ...fields}]`
- rendered values: a `Link` is a url (a page as its current path, `null` when it is gone or not published), a `Button` is `{text, url}` or `null`, `Items` is always a list with every field in every item, a missing field gets its default, `RichText` is cleaned HTML
- Blade: `<x-made-cms::strips :page="$page" />` renders every strip with `resources/views/strips/<key>.blade.php` (or the `view()` of its type) and passes `$strip`: `{{ $strip->kop }}`, `{!! $strip->intro !!}`; a missing view throws `MissingStripView`
- React or Vue: pass `RenderStrips::run($page)->toArray()` as a prop and map `strip.type` to a component of your own; print rich text with `dangerouslySetInnerHTML` or `v-html`
- put `data-cms-strip` with the strip id on the root element of every strip (required); `data-cms-field="<field>"` and `data-cms-items="<field>"` are optional and static
- a page link is the path in `cms_routes` without the prefix of a route group; a site under `/nl` adds that itself

## Rules, References, and Templates

Read before executing:

- no additional resource files for this skill

## Examples

- showing the tile to the office network: add `MADE_CMS_LAUNCHER_IPS=203.0.113.0/24` to the production `.env`
- a host feature test that snapshots full HTML: disable the launcher in `phpunit.xml` with `<env name="MADE_CMS_LAUNCHER_ENABLED" value="false"/>`
- pages in a second language: `Route::prefix('en')->group(fn () => Route::madeCms(fn (Page $page) => view('page', ['page' => $page])));`
- a first editor in production: `php artisan made-cms:editor editor@example.com --name="Menno Tempelaar" --role=admin`

## Anti-patterns

- do not treat the tile's visibility as authorisation; it only hides the door
- do not add a login form, guard or middleware for editors to the app; the package brings its own
- do not store roles on the app's users or write `cms_editors.roles` directly; roles change through `AssignRoles` so its rules hold
- do not build a menu or dashboard of your own that hides links as authorisation; the route middleware decides, `ListScreens` only feeds the menu
- do not queue or log `LoginCodeIssued`: its payload holds the plain code
- do not put a CDN or response cache in front of pages for allowed visitors without excluding the tile
- do not derive `key()` from the class name or change it once content uses it; the key is stored with every strip
- do not nest `Items` inside `Items`; one list level is supported
- do not put routes of the site below `Route::madeCms()`; they are never reached
- do not call the responder's parameter `$path`; the route parameter has that name and implicit binding would look the page up by id
- do not chain `Route::middleware(...)->madeCms()`; wrap the macro in `->group()` instead
- do not look pages up by `cms_pages.slug`; an address lives in `cms_routes`
- do not hand `$page->strips` to a view or a prop; it is the stored form with page ids and hidden strips, render through `RenderStrips`
- do not print a `Text` or `LongText` field unescaped; only `RichText` is cleaned HTML
- do not leave out `data-cms-strip` on the root element of a strip view; the edit mode finds the strip by it
- do not document package internals here; keep the skill focused on adoption in Laravel apps
