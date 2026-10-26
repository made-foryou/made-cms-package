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

- confirm the app is a Laravel 12 or 13 project on PHP 8.3+
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
- create one with `php artisan made-cms:editor <email> --name="..."` (empty password = code login only), or `CreateEditor::run(new NewEditor(email: ..., name: ..., password: null))` in a seeder
- editors log in through the panel the tile opens, or on the login page at `/made-cms` (the `routes.prefix`; link to it from invitation mails with `route('made-cms-package.login')`), both through the package's own `made-cms` session guard; it never logs anyone in to the app; check a CMS login with `auth('made-cms')->check()`
- set `APP_URL` and `MAIL_FROM_ADDRESS`: the login mail is sent right after the response through the app's mailer, never queued, and its link is built from `APP_URL`
- use a shared, persistent cache store in production: the login rate limiters live there
- name a page in the edit bar with `<meta name="made-cms:page" content="...">`; a fixed header can offset itself with the `--made-cms-bar-height` css variable

### 5. Use the actions

- `PublishPage::run($page)` publishes a page; query pages with `Page::query()->published()`
- listen to `EditorLoggedIn`, `EditorLoggedOut`, `TwoFactorEnabled` and `PagePublished` for side effects

## Rules, References, and Templates

Read before executing:

- no additional resource files for this skill

## Examples

- showing the tile to the office network: add `MADE_CMS_LAUNCHER_IPS=203.0.113.0/24` to the production `.env`
- a host feature test that snapshots full HTML: disable the launcher in `phpunit.xml` with `<env name="MADE_CMS_LAUNCHER_ENABLED" value="false"/>`
- a first editor in production: `php artisan made-cms:editor editor@example.com --name="Menno Tempelaar"`

## Anti-patterns

- do not treat the tile's visibility as authorisation; it only hides the door
- do not add a login form, guard or middleware for editors to the app; the package brings its own
- do not queue or log `LoginCodeIssued`: its payload holds the plain code
- do not put a CDN or response cache in front of pages for allowed visitors without excluding the tile
- do not document package internals here; keep the skill focused on adoption in Laravel apps
