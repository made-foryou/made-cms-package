<div align="center">
    <h1>Made Cms Package</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/made-foryou/made-cms-package"><img src="https://img.shields.io/packagist/v/made-foryou/made-cms-package.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/made-foryou/made-cms-package"><img src="https://img.shields.io/packagist/php-v/made-foryou/made-cms-package.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/made-foryou/made-cms-package"><img src="https://badge.laravel.cloud/badge/made-foryou/made-cms-package?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/made-foryou/made-cms-package/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/made-foryou/made-cms-package/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/made-foryou/made-cms-package"><img src="https://img.shields.io/packagist/dt/made-foryou/made-cms-package.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Content management system from Made

## Installation

You can install the package via Composer:

```bash
composer require made-foryou/made-cms-package
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="made-cms-package"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="made-cms-package-config"
```

### Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag="made-cms-package-migrations"
php artisan migrate
```

### Publishing the Views

```bash
php artisan vendor:publish --tag="made-cms-package-views"
```

### Publishing the Translations

```bash
php artisan vendor:publish --tag="made-cms-package-lang"
```

### Publishing the Public Assets

```bash
php artisan vendor:publish --tag="made-cms-package-assets"
```

## Usage

Issue a one-time login code for an editor. The plain code is only available in memory, so send it right away:

```php
use Made\CmsPackage\Access\Actions\IssueLoginCode;

$issued = IssueLoginCode::run('editor@example.com');

$issued->code;      // "482913", valid for 10 minutes by default
$issued->loginCode; // the stored LoginCode model, which only holds a hash
```

Publish a page and query pages:

```php
use Made\CmsPackage\Content\Actions\PublishPage;
use Made\CmsPackage\Content\Models\Page;

PublishPage::run($page);

Page::query()->published()->get();
Page::query()->recentlyEdited()->limit(3)->get();
```

Every write dispatches an event (`LoginCodeIssued`, `PagePublished`) that your application can listen to.

## Launcher

Once the package is installed, a MADE tile appears in the bottom-right corner of every full HTML page served through the `web` middleware group. Clicking it opens the Made CMS login panel over the site. There is nothing to publish or register: the middleware, markup, stylesheet, script and fonts (Nunito and Instrument Sans, both SIL Open Font License) are served straight from the package. They are deliberately not publishable, so they always update together.

The tile is only shown to:

- editors whose device was remembered by a previous CMS login (the encrypted `made_cms_editor` cookie), and
- visitors from the IP addresses in `MADE_CMS_LAUNCHER_IPS`.

```dotenv
MADE_CMS_LAUNCHER_IPS=203.0.113.5,198.51.100.0/24   # comma separated, CIDR and IPv6 allowed
MADE_CMS_LAUNCHER_ENABLED=false                      # switch the tile off, e.g. in phpunit.xml
MADE_CMS_LOCALE=nl                                   # language of the tile and panel, defaults to nl
```

Paths in `launcher.except` (default `nova*`, `telescope*`, `horizon*`, `pulse*`) and the CMS's own routes never get the tile. JSON, redirects, downloads, Inertia visits and error pages are left untouched.

Keep in mind:

- The tile only hides or shows the door. It never grants access; the login itself does.
- Behind a load balancer or proxy, the IP allowlist only works when the application trusts the proxy (`trustProxies`), otherwise every visitor has the proxy's IP address.
- Pages with the tile are sent as `Cache-Control: private, no-store`. A full-page cache in front of the application (a CDN "cache everything" rule, `spatie/laravel-responsecache`) can still store a page with the tile and show it to everyone; exclude those pages or disable the launcher.
- With a Content Security Policy, allow `'self'` for `script-src`, `style-src` and `font-src`. A Vite CSP nonce is added to the script automatically.

## Package Structure

The package is organised per bounded context. Each context holds its own actions, models, query builders, data objects and events:

```
src/
├── Access/     login codes and, later, passkeys, remembered devices and invitations
│   ├── Actions/    write operations, e.g. IssueLoginCode
│   ├── Builders/   custom Eloquent builders for reading, e.g. LoginCodeBuilder::active()
│   ├── Data/       immutable data objects
│   ├── Events/
│   └── Models/     plain table representations
├── Content/    pages, blocks and publishing
│   └── ...
├── Http/       technical layer: middleware (the launcher) and controllers, grouped per context
└── CmsPackageServiceProvider.php
```

All package tables use the `cms_` prefix.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Made Cms Package! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Made](https://github.com/made-foryou)
- [All Contributors](../../contributors)

## License

Made Cms Package is open-sourced software licensed under the [MIT license](LICENSE.md).
