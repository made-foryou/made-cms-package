# Release Notes

## [Unreleased](https://github.com/made-foryou/made-cms-package/compare/v0.1.0...1.x)

### Added

- Page files: the initial content of a page in `resources/cms/pages/<page>.json` (`path`, `title` and its `strips` with their content), with a JSON Schema (`resources/schema/page.json`). `ListPageImports` reads them into the `PageImport` and `StripImport` data objects and refuses a broken file with `InvalidPageImport`.
- `ValidateStripContent` checks the content of a strip against its type, fills fields without a value with an empty value and refuses what does not fit with `InvalidStripContent`, naming the field.
- Pages are found by their `path` (`cms_pages.path`, unique; `PageBuilder::forPath()` treats `lid-worden`, `/lid-worden` and `/lid-worden/` as the same page) and hold their strips in order: the `cms_strips` table and `Strip` model (`Page::strips()`), each strip with its type and content, serialised to `{id, type, content}`.
- `SaveStripType` writes a strip type to its JSON file, pretty printed in a fixed key order with a `$schema` reference, after checking it by the same rules as reading.
- `ListStripTypes` reads every strip type of the site from `resources/cms/strips/<key>.json`. Config keys `content.strips_path` and `content.pages_path` move the strip and page folders.
- Strip types: the kinds of strips the pages of a site are built from, defined as JSON in the host (a `label` and `fields` of the types `text`, `textarea`, `image`, `link` and `list`, a list one level deep), with a JSON Schema for autocomplete in the editor (`resources/schema/strip.json`). `ParseStripType` reads one definition into the `StripType` and `Field` data objects (`FieldType` enum) and refuses a broken one with `InvalidStripType`, naming the field.
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

- Screen labels moved to `screens.label.<screen>` in the translation files, next to the new `screens.group.<group>`; a host that published the translations republishes them (`made-cms-package-lang`).
- `NewEditor` takes the editor's `roles` (at least one) and `made-cms:editor` gains the `--role` option; without it the command asks for the roles.
- The default `routes.prefix` is now `made-cms` (was `cms`): every CMS route, the mail link and the launcher assets moved to `/made-cms/...`.
- `IssueLoginCode::run()` takes a `LoginCodeRequest` (address, browser binding, page to return to) and returns the link token too; `cms_login_codes` gains `attempts`, `session_hash`, `link_hash` and `return_to`.
- `ShowLauncher::run()` returns a `LauncherView` (hidden, tile or edit bar) instead of a boolean; `LauncherVisitor` gains the logged-in `editor`.

### Removed

- Scaffold placeholders: the `CmsPackage` class and facade, the placeholder command, migration, view, translation and config key.


## [v0.1.0](https://github.com/made-foryou/made-cms-package/compare/...v0.1.0) - 202x-xx-xx

Initial pre-release.
