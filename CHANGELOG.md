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

### Removed

- Scaffold placeholders: the `CmsPackage` class and facade, the placeholder command, migration, view, translation and config key.


## [v0.1.0](https://github.com/made-foryou/made-cms-package/compare/...v0.1.0) - 202x-xx-xx

Initial pre-release.
