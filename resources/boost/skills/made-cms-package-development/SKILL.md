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
- use a shared, persistent cache store in production: the login rate limiters and the stand-in password hash live there
- name a page in the edit bar with `<meta name="made-cms:page" content="...">`; a fixed header can offset itself with the `--made-cms-bar-height` css variable

### 5. Roles and screens

- every editor holds at least one fixed role (`Made\CmsPackage\Access\Enums\Role`): `admin` (every screen), `webmaster`, `content-manager`, `seo-specialist`, `owner`; the roles decide which CMS screens (`Screen` enum) the editor opens
- change roles with `AssignRoles::run(new RoleAssignment(by: $admin, editor: $editor, roles: [...]))`; it throws `RolesRejected` when the editor making the change may not (only admins and webmasters change roles, only an admin gives or takes `admin`, the last admin keeps it)
- read access with `ListScreens::run($editor)` or `CheckScreenAccess::run(new ScreenVisit($editor, Screen::Redirects))`, and find editors with `Editor::query()->withRole(Role::Admin)`
- the package registers a route per screen at `/made-cms/<screen>` (`route('made-cms-package.screens.redirects')`), guarded by `EnsureScreenAccess`; the pages screen is built, the other screens show that they are coming until they are
- guard a route of your own for a CMS screen with `->middleware(EnsureScreenAccess::using(Screen::Redirects))`
- the CMS start page is `/made-cms/start` (`route('made-cms-package.start')`): every logged-in editor opens it, with a menu of the screens of their roles; the edit bar and the login page link to it, so the host adds nothing

### 6. Strip types

- editors build pages from the site's strip types: one class per type in `app/Cms/Strips` that extends `Made\CmsPackage\Content\Strips\StripType` (config `strips.path` and `strips.namespace`); the package finds them on its own
- implement `key()` (fixed, lowercase letters, digits and dashes, e.g. `hero`) and `fields()`; optionally `label()`, `description()` (both shown in the catalogue editors add strips from), `view()` (the Blade view, default `strips.{key}`) and `with(array $values)` (extra data such as the latest news; it receives the rendered values and its keys land next to the fields)
- field types in `Made\CmsPackage\Content\Strips\Fields`: `Text`, `LongText`, `RichText` (cleaned HTML: p, strong, em, ul, ol, li, a), `Link` (`{"page": id}` or `{"url": "…"}`), `Button` (text + link), `Heading` (a text with its level, `{"text": "…", "tag": "h2"}`: `h1`–`h6` or `span` for no heading; `->tag('h1')` sets the level of the design, `h2` by default, `->tags(['h1', 'h2'])` limits the choice; a text stored while the field was a `Text` counts as the default level, so switching needs no migration), `Choice` (list, `value => label` or a backed enum) and `Items` (a list of items with the same fields, `min()`/`max()`, `itemLabel('Knop')` for "Knop toevoegen" in the side panel; every item gets a fixed `id`); every field takes `label()`, `hint()`, `required()` and `default()`; labels and hints are what editors see in the side panel, so write them in the editors' language
- strip values are validated with the package's own Dutch and English messages (`StripType::messages()`, `strips.validation.*` in the published translations), not the app's `validation.*` lines
- the field names `id`, `type` and `visible` are reserved, and `id` inside an item; a broken definition (also a heading level that is no tag, or a default level outside `tags()`) throws `InvalidStripType` or `DuplicateStripType` as soon as the catalogue (`app(StripTypes::class)`) is built

### 7. Use the actions

- a page holds its published strips (`strips`) and a draft (`draft_strips`, `null` without one, and again once a change is taken back); every strip has an `id`, a `type` key, `visible` and `values`
- `AddStrip::run($page, 'hero', $position)`, `MoveStrip::run($page, $stripId, $position)`, `RemoveStrip::run($page, $stripId)` (returns the removed `Strip`), `RestoreStrip::run($page, $strip, $position)` (puts a removed strip back with its own id, checked against its type), `UpdateStrip::run($page, $stripId, $values)` (merges with the stored values: pass only the fields to change, `null` clears one; throws a `ValidationException` keyed by field) and `SetStripVisibility::run($page, $stripId, $visible)` change the draft; visitors keep the published strips; every change reads the page again with its row locked, so changes at the same time never overwrite each other, and `$page` holds the stored state afterwards
- `PublishPage::run($page)` makes the draft the published version and refuses a draft with an invalid strip (errors keyed `strips.<id>.<field>`); `DiscardDraft::run($page)` goes back to the published version; query pages with `Page::query()->published()`
- `CompareDraft::run($page)` (`DraftChanges`) says what the draft changes: the strip ids it adds, changes (fields or visibility), removes or moves, `count()`, and whether the page can be published or discarded; a missing field counts as its default, key order and item ids do not count
- a position is the place among all strips of the draft, also hidden ones and strips of a type that left the catalogue; `ListEditableStrips::run($page)` lists them all in that order with `known`, `visible` and `summary` (`StripType::summary($values)`: the first field with text, else the titles of the items of a list)
- a strip whose type is no longer in the catalogue keeps its data; only `UpdateStrip` refuses it
- to change one item of a list, pass the whole list: items with the `id` of a stored item keep the fields you leave out, items without a known `id` are new, items you leave out are removed
- editors manage pages at `/made-cms/pages` (`route('made-cms-package.screens.pages')`): the tree in menu order, a new page at `pages.create`, one page at `pages.show`, deleting and restoring; the host adds nothing
- pages form a tree: `parent_id`, `position` among the siblings and `in_menu`; the address is the parent's address plus the slug (`/` + slug at the top and under the home page) and every write keeps the addresses of the pages under it in step
- create and change pages with `CreatePage::run(new NewPage(title: 'Team', slug: null, parentId: $about->id))`, `UpdatePage::run($page, new PageChanges(...))`, `MovePage::run($page, $parentId, $position)`, `DeletePage::run($page)` and `RestorePage::run($page)`; read the tree with `ListPages::run()` (`PageTree`, `->flatten()` in menu order, `->inMenu()` for a site menu); never write `parent_id`, `position` or `cms_routes.path` yourself
- refusals are exceptions: `PagePathTaken`, `InvalidPageSlug`, `CircularPageMove`, `HomePageProtected` (the home page keeps `/`, stays at the top and is never deleted), `PageHasChildren` (move or delete the pages below first)
- deleting is a soft delete that frees the address; `made-cms:prune` removes pages deleted more than `pages.keep_deleted_days` (30) days ago and the package schedules it daily, so run the scheduler (`schedule:run`) in production
- listen to `EditorLoggedIn`, `EditorLoggedOut`, `EditorRolesChanged`, `TwoFactorEnabled`, `PageCreated`, `PageUpdated`, `PageMoved` (old and new address), `PageDeleted`, `PageRestored`, `StripAdded`, `StripMoved`, `StripRemoved`, `StripUpdated`, `StripVisibilityChanged`, `DraftDiscarded` and `PagePublished` for side effects; the content events (`Page*`, `Strip*`, `DraftDiscarded`, `PagePublished`) arrive after the commit of the transaction they are dispatched in

### 8. Serve the pages

- serve the CMS pages with one line as the **last** line of `routes/web.php`: `Route::madeCms(fn (Page $page) => view('page', ['page' => $page]))` (Blade), `Route::madeCms(fn (Page $page) => Inertia::render('Page', ['title' => $page->title, 'strips' => RenderStrips::run($page)->toArray()]))` (Inertia), or `Route::madeCms(ShowPage::class)` with an invokable class
- the macro catches every `GET` address no route above it claims, looks up the published page there and hands it to the responder; no page or a page that is not published answers 404; routes of the site above it win
- it takes the prefix and middleware of a surrounding group, returns the route without a name (chain `->name()` or `->middleware()`), can be used more than once and works with `route:cache`
- every page has one address: a `GET` or `HEAD` for another form (`/Over-Ons`, `/over-ons/`, `//over-ons`) gets a `301` to the lowercase address without a trailing slash, under the group prefix and with the query string; link to `$page->route->path`, never to a form of your own
- the edit mode: the CMS loads a page in an iframe on the same origin with `?made-cms=edit`; for a logged-in editor the responder gets the page whatever its status and `RenderStrips`/`<x-made-cms::strips>` render the draft, sent with `no-store`, `X-Frame-Options: SAMEORIGIN` and `noindex` and without the launcher; the responder needs no code for it, but must cope with a draft page (`published_at` can be `null`)
- the preview: `?made-cms=preview` serves the same for an editor outside the frame ("Voorvertoning" opens it in a new tab); visitors and guests get what they always get
- the address of a page is `$page->route->path` (`/` for home, `/over-ons/team` nested), stored in `cms_routes`; look up what a visitor sees with `FindPublishedPage::run(PagePath::fromString($path))`

### 9. Render the strips

- `RenderStrips::run($page)` gives the strips a visitor sees: published (the draft in the edit mode), in order, without hidden strips or strips of a type that left the catalogue; `->toArray()` is `[{id, type, ...fields}]`
- rendered values: a `Link` is a url (a page as its current path, `null` when it is gone or not published, or when the address is one the validation would refuse), a `Button` is `{text, url}` or `null`, a `Heading` is `{text, tag}` or `null` without a text, `Items` is always a list with the `id` and every field in every item (use the `id` as the key of a list), a missing field gets its default, `RichText` is cleaned HTML (also a value a seeder or an import stored, or a default) or `null`
- Blade: `<x-made-cms::strips :page="$page" />` renders every strip with `resources/views/strips/<key>.blade.php` (or the `view()` of its type) and passes `$strip`: `{{ $strip->kop }}`, `{!! $strip->intro !!}`; a missing view throws `MissingStripView`; print a heading with `<x-made-cms::heading :heading="$strip->kop" field="kop" class="hero__kop" />` (the element of its level, nothing without a text; in a list `field` names the field of the item)
- React or Vue: pass `RenderStrips::run($page)->toArray()` as a prop and map `strip.type` to a component of your own; print rich text with `dangerouslySetInnerHTML` or `v-html`, a heading with its tag (`const Kop = strip.kop?.tag` in React, `<component :is="strip.kop.tag">` in Vue)
- put `data-cms-strip` with the strip id on the root element of every strip (required); `data-cms-field="<field>"` and `data-cms-items="<field>"` are optional and static
- the edit shell (`/made-cms/pages/{page}/edit`, "Bewerken" in the edit bar) outlines every `data-cms-strip` in the frame, also strips rendered in the browser after load; a visible strip without it is reported in the browser console, and links and forms of the site do nothing in the frame
- without a chosen strip the side panel shows the opbouw: editors add strips from the catalogue (every strip type, by `label()` with its `description()`), move them by dragging or with the arrow keys and remove them with "Ongedaan maken"; a strip of a type the site no longer has stays listed as "Niet op de site" and can only be removed. Its JSON routes (`made-cms-package.pages.strips.index`, `.store`, `.position`, `.destroy`, `.restore`) need no code in the app; the frame reloads after every change, so a strip view only has to render what it gets
- choosing a strip opens its fields in the side panel; editors' changes are saved as the draft (always every field, through `UpdateStrip`), so a strip view needs nothing extra to show them; the panel's JSON routes (`made-cms-package.pages.strips.*`) need no code in the app
- watching along: an element with `data-cms-field` follows what the editor types without a reload, and clicking it on the site opens the field in the panel; anything else reloads the frame after the save. Put `data-cms-field` on the element that holds only the text (a text, long text or button), on an element with `href` (a link, a button), or on the element that prints rich text unescaped. In `data-cms-items` render one direct child per item, in order; a field inside it uses the key of the item's field (`titel`)
- "Ongedaan maken" in the panel takes the last saved change of a strip back, one step per click, for as long as the shell is open
- the bar of the shell shows "Concept · n wijzigingen" (`CompareDraft`), publishes with "Publiceren" (`PublishPage`; a strip that breaks its definition is named and chosen instead), throws the draft away with "Concept weggooien" (`DiscardDraft`) and opens "Voorvertoning"; every editor who opens `Screen::Pages` may publish; its JSON routes (`made-cms-package.pages.draft.show`, `.draft.destroy`, `made-cms-package.pages.publish`) need no code in the app
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
- do not create pages or addresses with `Page::create()` or `$page->route()->create()` in app code; `CreatePage` keeps the tree and the addresses consistent
- do not put a CDN or response cache in front of pages for allowed visitors without excluding the tile
- do not derive `key()` from the class name or change it once content uses it; the key is stored with every strip
- do not nest `Items` inside `Items`; one list level is supported
- do not put routes of the site below `Route::madeCms()`; they are never reached
- do not call the responder's parameter `$path`; the route parameter has that name and implicit binding would look the page up by id
- do not chain `Route::middleware(...)->madeCms()`; wrap the macro in `->group()` instead
- do not look pages up by `cms_pages.slug`; an address lives in `cms_routes`
- do not hand `$page->strips` to a view or a prop; it is the stored form with page ids and hidden strips, render through `RenderStrips`
- do not print a `Text` or `LongText` field unescaped; only `RichText` is cleaned HTML
- do not hard-code the element of a `Heading` or style headings by tag (`h2 { … }`); print the tag from the value and give it its look with a class, because the level is a value of the field, not of the view
- do not send `X-Frame-Options: DENY` or `Content-Security-Policy: frame-ancestors 'none'` from the site's middleware or web server; the edit mode needs a frame of the same origin (`SAMEORIGIN`, `frame-ancestors 'self'`)
- do not let a full-page cache store responses with `?made-cms=edit` or `?made-cms=preview`
- do not leave out `data-cms-strip` on the root element of a strip view; the edit shell finds the strip by it
- do not document package internals here; keep the skill focused on adoption in Laravel apps
