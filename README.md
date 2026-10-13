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
