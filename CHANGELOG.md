# Release Notes

## [Unreleased](https://github.com/made-foryou/made-cms-package/compare/v0.1.0...1.x)

### Added

- `Route::madeCms($responder)`: the site serves the CMS pages with one line, as the last of its web routes. Every `GET` address no route above it claims looks up the published page there and hands it to the responder of the site (a closure or an invokable class) as `Page`; an address without a page, or a page that is not published, answers 404. The route takes the prefix and middleware of its group, has no name (chain `->name()` yourself) and works with `route:cache`. Middleware `ResolvePage` does the lookup.
- Addresses of the site in the new `cms_routes` table (`Route` model, one unique `path` per row pointing at its content through `routable`), with `Page::route()`, `RouteBuilder::atPath()` and `PageBuilder::atPath()`. Pages are stored under the morph alias `cms_page`, added to the host's morph map without enforcing one.
- `FindPublishedPage` action and the `PagePath` data object (an address normalised to `/` or `/over-ons/team`).
- Page factory state `at($path)` and `RouteFactory`.

- Strips per page with a draft and a published version: `cms_pages` gains `strips` (the published strips, `[]` by default) and `draft_strips` (the draft editors work on, `null` when there is none). Every strip has a fixed `id` (uuid), its strip type `type`, `visible` and its `values`.
- Actions `AddStrip` (type and position, filled with the defaults of the type), `MoveStrip`, `RemoveStrip`, `UpdateStrip` (checks the values against the strip type, cleans them and throws a `ValidationException` keyed by field), `SetStripVisibility` and `DiscardDraft`. They all write to the draft, so visitors keep seeing the published version, and dispatch `StripAdded`, `StripMoved`, `StripRemoved`, `StripUpdated`, `StripVisibilityChanged` or `DraftDiscarded`. A strip that is not on the page throws `StripNotFound`, a position outside the page `InvalidStripPosition`.
- Data objects `Strip` and `PageStrips` for the stored strips of a page.
- A strip of a type that is no longer in the catalogue keeps its data: it moves, hides, publishes and is discarded like any strip; only `UpdateStrip` refuses it (`UnknownStripType`).
- Page factory states `withStrips()` and `withDraft()`.

- Strip types: the site defines the kinds of strips editors build pages from, one class per type in `app/Cms/Strips` extending `Made\CmsPackage\Content\Strips\StripType` (fixed `key()`, `fields()`, optional `label()`, `description()`, `view()` and `with()`). A strip type derives its validation rules, labels, defaults and stored values.
- Field types `Text`, `LongText`, `RichText` (cleaned HTML: p, strong, em, ul, ol, li, a), `Link` (`{"page": id}` or `{"url": "…"}`), `Button`, `Choice` and `Items` in `Made\CmsPackage\Content\Strips\Fields`.
- The strip type catalogue `StripTypes` (`all()`, `has()`, `find()`), bound as a singleton; it checks every definition and throws `InvalidStripType`, `DuplicateStripType` or `UnknownStripType`.
- Config keys `strips.path` and `strips.namespace`.
- Dutch and English translations for the link validation and the button fields (`strips`).
- Runtime dependency `symfony/html-sanitizer` (`^7.4.13||^8.0.13`, the versions without known advisories).
- Start page at `/made-cms/start` (route `made-cms-package.start`) for every logged-in editor (`EnsureEditor` middleware; guests go to the login page): a note of the day, the question what to work on and the four blocks of the design (where you left off, what needs attention, how your site is doing, quick links), in light and dark mode and on the phone. The blocks show the static examples of the design for the editor's role for now and never link to a screen the editor does not open.
- The CMS menu: the screens of the editor's roles under "Je site", "Verzamelingen", "Vindbaarheid" and "Beheer" (`ScreenGroup` enum, `Screen::group()`), behind a button that peeks open once and then keeps the state the editor left it in.
- A route per screen at `/made-cms/<screen>` (route `made-cms-package.screens.<screen>`), guarded by `EnsureScreenAccess`; until a screen is built it shows that it is coming.
- Links to the start page from "Verder waar je was" on the login page and from the edit bar.
- The asset route also serves the stylesheet and script of the CMS pages (`cms.css`, `cms.js`).
- Dutch and English translations for the CMS pages, the start page and the screen groups.
- Roles and screens: every editor holds one or more fixed roles (`Role` enum: `admin`, `webmaster`, `content-manager`, `seo-specialist`, `owner`) in the new `roles` column of `cms_editors`. `Role::screens()` grants the screens of the CMS (`Screen` enum, one per menu item); `admin` opens every screen. `ListScreens` lists the screens of an editor, `CheckScreenAccess` decides whether a screen opens, and `EditorBuilder::withRole()` finds the editors with a role.
- `AssignRoles` action with the `RoleAssignment` data object, the `EditorRolesChanged` event and the `RolesRejected` exception: editors who open the users and roles screen change roles, only an admin gives or takes the admin role, the last admin keeps it and every editor keeps at least one role.
- `EnsureScreenAccess` middleware for the routes of a CMS screen (`EnsureScreenAccess::using(Screen::Redirects)`): guests go to the login page, editors without a role for the screen get a 403.
- Dutch and English translations for roles and screens.
- Login page at `/made-cms` (route `made-cms-package.login`), the second door for a direct link: the same login steps as the panel as a full page, in light and dark mode and on the phone, ending at "Verder waar je was" (static for now). `OpenLoginPage` action decides the step it opens on.
- Bounded contexts `Access` and `Content` with Actions (`lorisleiva/laravel-actions`), custom Eloquent Builders, Data objects and Events.
- `IssueLoginCode` action with the `cms_login_codes` table, `LoginCode` model and `LoginCodeIssued` event.
- `PublishPage` action with the `cms_pages` table, `Page` model, `PageStatus` enum and `PagePublished` event.
- Config keys `routes.prefix`, `routes.middleware`, `login_code.length` and `login_code.ttl_minutes`.
- Dutch and English translations for page statuses.
- Arch tests that enforce the context layout.
- Runtime dependencies `illuminate/database` and `lorisleiva/laravel-actions`.
- Launcher: the MADE tile in the bottom-right corner of every web page that opens the CMS login panel (e-mail step). It is added to the `web` middleware group automatically (`InjectLauncher`) and only shown to remembered editors and allowed IP addresses (`ShowLauncher` action).
- Config keys `launcher.enabled`, `launcher.allowed_ips`, `launcher.locale` and `launcher.except` (env `MADE_CMS_LAUNCHER_ENABLED`, `MADE_CMS_LAUNCHER_IPS`, `MADE_CMS_LOCALE`).
- Route `made-cms-package.launcher.asset` that serves the launcher stylesheet, script and self-hosted fonts (Nunito, Instrument Sans; SIL Open Font License) without middleware.
- Dutch and English launcher translations.
- Runtime dependencies `illuminate/contracts`, `illuminate/http`, `illuminate/routing`, `illuminate/view`, `symfony/http-foundation` and `symfony/http-kernel`.
- Editors: CMS accounts of their own in `cms_editors` (`Editor` model, `CreateEditor` action, `EditorCreated` event) and the `made-cms:editor` command. They log in through the session guard `made-cms`, which the package registers next to the host's guards.
- The full login flow from the website: e-mail step, login code by mail with a link that only works in the requesting browser, password with "remember this device" (30 days) and an edit bar with logout after logging in. Endpoints under `cms/` (`session`, `login/email`, `login/code`, `login/code/verify`, `login/password`, `login/two-factor`, `login/link/{token}`, `two-factor/setup`, `two-factor/confirm`, `logout`), rate limited and never revealing whether an address has an account.
- Two-factor authentication for password logins: authenticator app (TOTP) with QR code and recovery codes, offered after a password login. Runtime dependencies `pragmarx/google2fa` and `bacon/bacon-qr-code`.
- The launcher greets an editor by first name on a device that logged in with the same address before; the `made_cms_editor` cookie now holds a keyed hash of the address, the first name and the last method.
- The login mail of the design (1.10): the code in the subject and in large type, a button that logs in the requesting browser, its expiry time, the device and browser it was asked from, a plain text version, and the sender name "Made CMS". Its views (`made-cms-package::mail.login-code`) can be published with the `made-cms-package-views` tag.
- Config keys `auth.guard`, `auth.remember_days`, `login_code.max_attempts`, `mail.from_name`, `mail.help_url` (env `MADE_CMS_HELP_URL`), `two_factor.window` and `two_factor.recovery_codes`.
- Dutch and English translations for every login step, the login mail and the command.
- Runtime dependencies `illuminate/auth`, `illuminate/cache`, `illuminate/console`, `illuminate/cookie`, `illuminate/hashing`, `illuminate/mail`, `illuminate/notifications` and `illuminate/session`.

### Changed

- `cms_pages.slug` is no longer unique: the address of a page is unique in `cms_routes`, so pages under different parents can share a slug. A host that already ran the `cms_pages` migration runs it again (`migrate:fresh`) or drops the unique index on `slug` itself.
- `PublishPage` publishes the draft: its strips become the published strips and the draft is cleared, also on a page that is already published (`published_at` is the last publication). It refuses a draft with a strip that breaks its strip type, hidden strips included, with a `ValidationException` keyed by `strips.<strip id>.<field>`, and still throws `PageAlreadyPublished` for a published page without a draft. A host that already ran the `cms_pages` migration runs it again (`migrate:fresh`) or adds the `strips` (json) and `draft_strips` (json, nullable) columns itself.

- Screen labels moved to `screens.label.<screen>` in the translation files, next to the new `screens.group.<group>`; a host that published the translations republishes them (`made-cms-package-lang`).
- `NewEditor` takes the editor's `roles` (at least one) and `made-cms:editor` gains the `--role` option; without it the command asks for the roles.
- The default `routes.prefix` is now `made-cms` (was `cms`): every CMS route, the mail link and the launcher assets moved to `/made-cms/...`.
- `IssueLoginCode::run()` takes a `LoginCodeRequest` (address, browser binding, page to return to) and returns the link token too; `cms_login_codes` gains `attempts`, `session_hash`, `link_hash` and `return_to`.
- `ShowLauncher::run()` returns a `LauncherView` (hidden, tile or edit bar) instead of a boolean; `LauncherVisitor` gains the logged-in `editor`.

### Removed

- Scaffold placeholders: the `CmsPackage` class and facade, the placeholder command, migration, view, translation and config key.


## [v0.1.0](https://github.com/made-foryou/made-cms-package/compare/...v0.1.0) - 202x-xx-xx

Initial pre-release.
