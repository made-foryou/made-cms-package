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

Create an editor account. Editors are CMS accounts of their own (the `cms_editors` table): they live next to the users of your site and never mix with them. Leave the password empty for an editor who only logs in with a code from the mail:

```bash
php artisan made-cms:editor editor@example.com --name="Menno Tempelaar" --role=admin
```

Every editor has at least one role. Pass `--role` once per role, or leave it out to pick them from a list.

Or from code, for example in a seeder:

```php
use Made\CmsPackage\Access\Actions\CreateEditor;
use Made\CmsPackage\Access\Data\NewEditor;
use Made\CmsPackage\Access\Enums\Role;

CreateEditor::run(new NewEditor(email: 'editor@example.com', name: 'Menno Tempelaar', password: null, roles: [Role::Admin]));
```

Build a page from strips and publish it. Every strip action works on the draft; visitors see the published strips until the draft is published:

```php
use Made\CmsPackage\Content\Actions\AddStrip;
use Made\CmsPackage\Content\Actions\DiscardDraft;
use Made\CmsPackage\Content\Actions\MoveStrip;
use Made\CmsPackage\Content\Actions\PublishPage;
use Made\CmsPackage\Content\Actions\RemoveStrip;
use Made\CmsPackage\Content\Actions\SetStripVisibility;
use Made\CmsPackage\Content\Actions\UpdateStrip;
use Made\CmsPackage\Content\Models\Page;

$strip = AddStrip::run($page, 'hero', 0);                    // type key and position
UpdateStrip::run($page, $strip->id, ['kop' => 'Welkom']);    // validated against the strip type
MoveStrip::run($page, $strip->id, 2);
SetStripVisibility::run($page, $strip->id, false);
RemoveStrip::run($page, $strip->id);

PublishPage::run($page);   // the draft becomes the published version
DiscardDraft::run($page);  // or: back to the published version

Page::query()->published()->get();
Page::query()->recentlyEdited()->limit(3)->get();
```

Every write dispatches an event that your application can listen to: `EditorCreated`, `EditorRolesChanged`, `LoginCodeIssued`, `EditorLoggedIn`, `EditorLoggedOut`, `TwoFactorEnabled`, `StripAdded`, `StripMoved`, `StripRemoved`, `StripUpdated`, `StripVisibilityChanged`, `DraftDiscarded` and `PagePublished`.

## Roles and screens

Every editor holds one or more fixed roles. A role decides which screens of the CMS the editor opens; an editor with several roles opens the screens of all of them.

| Role | Value | Screens |
|---|---|---|
| Admin | `admin` | every screen, also screens added later |
| Webmaster | `webmaster` | pages, news, media, messages, users and roles, forms, backups, log, domain |
| Content manager | `content-manager` | pages, news, media, messages |
| SEO specialist | `seo-specialist` | pages, meta tags, alt texts, links, redirects, sitemap, keywords |
| Owner | `owner` | pages, news, messages |

```php
use Made\CmsPackage\Access\Actions\AssignRoles;
use Made\CmsPackage\Access\Actions\CheckScreenAccess;
use Made\CmsPackage\Access\Actions\ListScreens;
use Made\CmsPackage\Access\Data\RoleAssignment;
use Made\CmsPackage\Access\Data\ScreenVisit;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;

ListScreens::run($editor);                                      // the screens of all roles, in menu order
CheckScreenAccess::run(new ScreenVisit($editor, Screen::Redirects));
Editor::query()->withRole(Role::Admin)->get();

AssignRoles::run(new RoleAssignment(by: $admin, editor: $editor, roles: [Role::ContentManager, Role::SeoSpecialist]));
```

`AssignRoles` replaces all roles of an editor and throws `RolesRejected` when the change is not allowed:

- the editor making the change must open the users and roles screen (admins and webmasters);
- only an admin gives or takes the admin role;
- the last admin keeps the admin role;
- every editor keeps at least one role.

In the menu the screens sit in four groups, in this order: your site (pages), collections (news, media, messages), findability (meta tags, alt texts, links, redirects, sitemap, keywords) and management (users and roles, forms, backups, log, domain). `Screen::group()` gives the `ScreenGroup` of a screen.

Every screen has its own route, `/made-cms/<screen>` (named `made-cms-package.screens.<screen>`, e.g. `/made-cms/redirects`). Until a screen is built, it shows that it is coming. The routes are guarded by the `EnsureScreenAccess` middleware: a guest is sent to the login page, an editor without a role for the screen gets a 403.

```php
use Made\CmsPackage\Http\Middleware\EnsureScreenAccess;

Route::get('redirects', RedirectsController::class)->middleware(EnsureScreenAccess::using(Screen::Redirects));
```

## Start page

Once logged in, editors start at `/made-cms/start` (named `made-cms-package.start`): a short note of the day, the question what they want to work on, and four blocks that are the same for every role: where you left off, what needs attention, how your site is doing and quick links. The menu behind the button in the top-left corner lists the screens of the editor's roles, grouped as above; it peeks open once and then stays as the editor left it. The page follows the editor's light or dark mode.

The blocks still show the static examples of the design for the editor's role (an admin or an editor with several roles gets the combined example). They never link to a screen the editor does not open. Guests are sent to the login page.

## Strip types

Editors build pages from strips: a hero, a row of cards, opening hours. The site decides which kinds of strips exist, one PHP class per strip type in `app/Cms/Strips`. The package finds every class in that folder and its sub-folders and offers them as the catalogue editors pick from.

```php
namespace App\Cms\Strips;

use App\Enums\ButtonStyle;
use Made\CmsPackage\Content\Strips\Fields\Choice;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Link;
use Made\CmsPackage\Content\Strips\Fields\RichText;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Content\Strips\StripType;

final class Hero extends StripType
{
    public function key(): string
    {
        return 'hero';
    }

    public function fields(): array
    {
        return [
            Text::make('label')->hint('Korte regel boven de kop.'),
            Text::make('kop')->required(),
            RichText::make('intro'),
            Items::make('knoppen', [
                Text::make('tekst')->required(),
                Link::make('gaat_naar')->required(),
                Choice::make('stijl', ButtonStyle::class),
            ])->min(1)->max(2),
        ];
    }
}
```

`key()` is stored with every strip, so it never changes once content uses it, also not when you rename the class. It uses lowercase letters, digits and dashes. Optionally override `label()` (the name in the catalogue, by default the class name), `description()`, `view()` (by default `strips.{key}`) and `with(array $values)` (extra data to render with, such as the latest news).

| Field | Stored value | Notes |
|---|---|---|
| `Text::make('kop')` | string | One line, at most 255 characters. |
| `LongText::make('intro')` | string | Plain text over several lines. |
| `RichText::make('tekst')` | HTML | Only `p`, `strong`, `em`, `ul`, `ol`, `li` and `a href` (http, https, mailto, tel, relative). Other markup keeps its text but loses its tags; scripts, styles and embeds disappear. |
| `Link::make('gaat_naar')` | `{"page": 12}` or `{"url": "…"}` | A page of the site, which keeps working when its path changes, or an address: `https://`, `mailto:`, `tel:`, `/path` or `#anchor`. |
| `Button::make('knop')` | `{"text": "…", "link": {…}}` | A text and a link. |
| `Choice::make('stijl', $options)` | string | Options as a list, as `value => label`, or a backed enum (labels from its `label()` method when it has one). |
| `Items::make('knoppen', [...])` | list of items | Every item has the given fields; `min()`/`max()` limit the count, `required()` asks for at least one. One level deep. |

Every field takes `label()` (by default the key as a sentence: `gaat_naar` becomes "Gaat naar"), `hint()`, `required()` and `default()`. Fields are immutable: each of these returns a changed copy.

The field names `id`, `type` and `visible` are reserved for the strip itself. Every definition is checked when the catalogue is built; a mistake (an invalid or duplicate key, a reserved field, an invalid `min`/`max`, a choice without options) throws an exception that names the strip type.

Read the catalogue through `Made\CmsPackage\Content\Strips\StripTypes`: `app(StripTypes::class)->all()`, `->has('hero')` and `->find('hero')`. A strip type derives its validation rules (`rules()`, with `attributes()` for the messages), the values of a new strip (`defaults()`) and the stored form of validated values (`normalize()`).

Config keys: `strips.path` (default `app_path('Cms/Strips')`) and `strips.namespace` (default `App\Cms\Strips`).

## Launcher

Once the package is installed, a MADE tile appears in the bottom-right corner of every full HTML page served through the `web` middleware group. Clicking it opens the Made CMS login panel over the site. There is nothing to publish or register: the middleware, markup, stylesheet, script and fonts (Nunito and Instrument Sans, both SIL Open Font License) are served straight from the package. They are deliberately not publishable, so they always update together.

The tile is only shown to:

- editors whose device was remembered by a previous CMS login (the encrypted `made_cms_editor` cookie), and
- visitors from the IP addresses in `MADE_CMS_LAUNCHER_IPS`.

A logged-in editor gets the edit bar instead, from any IP address.

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
- With a Content Security Policy, allow `'self'` for `script-src`, `style-src`, `font-src` and `connect-src`. A Vite CSP nonce is added to the script automatically.

## Logging in

Editors log in through one of two doors:

- **from the website:** the panel the tile opens, over the page they are on; once logged in, the page gets the edit bar;
- **with a direct link:** the login page at `/made-cms` (the `routes.prefix`), for an invitation mail or a bookmark. It opens for every visitor, also without the tile, and follows the visitor's light or dark mode. Once logged in it ends at "Verder waar je was" (still a static example for now), with a link to the start page. An editor who is already logged in lands there straight away.

Both doors take the same three steps:

1. their e-mail address;
2. a login code by mail (with a link that logs in the same browser) or their password;
3. the code from the mail, or after a password the code from their authenticator app when they added one.

After a password login without an authenticator app, the panel offers to add one: scan a QR code, confirm a code and save the recovery codes. Once logged in, the page gets an edit bar with the name of the page, a link to the start page and a logout button.

How it is protected:

- **A guard of its own.** The package registers the session guard `made-cms` (provider `made-cms`, model `Editor`) next to your guards. Logging in to the CMS never logs anyone in to your site, and logging out of the CMS keeps the rest of the session. Define `auth.guards.made-cms` yourself to override it, or rename it with `made-cms-package.auth.guard`.
- **No account enumeration.** Every e-mail address gets the same answer in the same time. The panel only greets an editor by name on a device that logged in with that address before (the encrypted `made_cms_editor` cookie, which never holds the plain address).
- **Codes and links are bound to the browser** that asked for them, work once, expire after `login_code.ttl_minutes` (10) and stop after `login_code.max_attempts` (5) wrong tries. Opening the mail link only shows a confirmation page, so mail scanners never use it up.
- **Rate limits** per typed address and ip address (`made-cms-package.login-code`, `made-cms-package.login`, `made-cms-package.two-factor`). They need a cache store that is shared between your servers and survives a request.
- **Remember me** keeps an editor logged in for `auth.remember_days` (30) days.
- **Mail** goes out right after the response through your mail configuration, never through the queue, so the plain code is never stored. Set `MAIL_FROM_ADDRESS` and `APP_URL`: the link in the mail is built from `APP_URL`, never from the request. The sender name is "Made CMS" (`mail.from_name`); set `MADE_CMS_HELP_URL` to link to help with logging in. Publish the views (`made-cms-package-views`) to change the mail.

All CMS routes live under `routes.prefix` (default `made-cms`): the login page, the start page, the screens, the endpoints the launcher calls, the mail link and the launcher assets.

Config keys: `routes.prefix`, `auth.guard`, `auth.remember_days`, `login_code.length`, `login_code.ttl_minutes`, `login_code.max_attempts`, `mail.from_name`, `mail.help_url`, `two_factor.window` and `two_factor.recovery_codes`.

The edit bar names the page after its `<title>` without the site name. Set a name of your own with:

```html
<meta name="made-cms:page" content="Trainingstijden">
```

The bar is 56px high and moves the page down by setting `padding-top` on `<html>`. A fixed header can use the `--made-cms-bar-height` variable that is set on `<html>` while the bar shows.

## Package Structure

The package is organised per bounded context. Each context holds its own actions, models, query builders, data objects and events:

```
src/
├── Access/     editors, roles and screens, login codes, passwords, authenticator apps and remembered devices
│   ├── Actions/    write operations, e.g. IssueLoginCode
│   ├── Builders/   custom Eloquent builders for reading, e.g. LoginCodeBuilder::active()
│   ├── Data/       immutable data objects
│   ├── Events/
│   └── Models/     plain table representations
├── Content/    pages, strips and publishing
│   ├── Strips/     the strip type definition API: StripType, the field types and the StripTypes catalogue
│   └── ...
├── Console/    technical layer: artisan commands
├── Http/       technical layer: middleware (the launcher), controllers grouped per context, the login session
├── Support/    context-free helpers: site name and url, launcher locale, QR code
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
