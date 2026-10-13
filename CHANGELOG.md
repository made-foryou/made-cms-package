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

### Removed

- Scaffold placeholders: the `CmsPackage` class and facade, the placeholder command, migration, view, translation and config key.


## [v0.1.0](https://github.com/made-foryou/made-cms-package/compare/...v0.1.0) - 202x-xx-xx

Initial pre-release.
