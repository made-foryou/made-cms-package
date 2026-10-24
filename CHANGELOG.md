# Release Notes

## [Unreleased](https://github.com/made-foryou/made-cms-package/compare/v0.1.0...1.x)

### Added

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

- `IssueLoginCode::run()` takes a `LoginCodeRequest` (address, browser binding, page to return to) and returns the link token too; `cms_login_codes` gains `attempts`, `session_hash`, `link_hash` and `return_to`.
- `ShowLauncher::run()` returns a `LauncherView` (hidden, tile or edit bar) instead of a boolean; `LauncherVisitor` gains the logged-in `editor`.

### Removed

- Scaffold placeholders: the `CmsPackage` class and facade, the placeholder command, migration, view, translation and config key.


## [v0.1.0](https://github.com/made-foryou/made-cms-package/compare/...v0.1.0) - 202x-xx-xx

Initial pre-release.
