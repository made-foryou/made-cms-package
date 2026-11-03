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

Every screen has its own route, `/made-cms/<screen>` (named `made-cms-package.screens.<screen>`, e.g. `/made-cms/redirects`). The pages screen is built; the other screens show that they are coming until they are. The routes are guarded by the `EnsureScreenAccess` middleware: a guest is sent to the login page, an editor without a role for the screen gets a 403.

```php
use Made\CmsPackage\Http\Middleware\EnsureScreenAccess;

Route::get('redirects', RedirectsController::class)->middleware(EnsureScreenAccess::using(Screen::Redirects));
```

## Start page

Once logged in, editors start at `/made-cms/start` (named `made-cms-package.start`): a short note of the day, the question what they want to work on, and four blocks that are the same for every role: where you left off, what needs attention, how your site is doing and quick links. The menu behind the button in the top-left corner lists the screens of the editor's roles, grouped as above; it peeks open once and then stays as the editor left it. The page follows the editor's light or dark mode.

The blocks still show the static examples of the design for the editor's role (an admin or an editor with several roles gets the combined example). They never link to a screen the editor does not open. Guests are sent to the login page.

## Pages screen

The pages screen at `/made-cms/pages` lists every page of the site in the order of the menu, the pages under a page indented below it, with its address, its status and what still needs doing. Every editor who opens `Screen::Pages` uses it; the menu lists the same pages under "Je site".

- **New page** (`/made-cms/pages/new`) asks one question per step: the name, from which the address follows, and the place: at the top or under another page. The page starts as an empty draft, last in the menu, and opens on the site to be filled.
- **One page** (`/made-cms/pages/{page}`) changes the name, the address, the parent and whether the page is in the menu. The pages under it move along, and their addresses follow.
- **Deleting** hides the page from visitors and editors and frees its address. It stays under "Verwijderde pagina’s" for 30 days (`pages.keep_deleted_days`) and comes back with "Terugzetten". The package schedules `made-cms:prune` daily to remove older deleted pages for good, so the host only needs the scheduler (`php artisan schedule:run` every minute).

The address of a page is the address of its parent plus its slug; a page at the top or under the home page gets `/` plus its slug. The home page keeps `/`, stays at the top and cannot be deleted. A page with pages under it is deleted after they are moved or deleted.

The same rules are available as actions:

```php
use Made\CmsPackage\Content\Actions\CreatePage;
use Made\CmsPackage\Content\Actions\MovePage;
use Made\CmsPackage\Content\Data\NewPage;

$team = CreatePage::run(new NewPage(title: 'Team', slug: null, parentId: $about->id)); // /over-ons/team
MovePage::run($team, parentId: null, position: 0); // /team, first in the menu
```

`UpdatePage`, `DeletePage`, `RestorePage` and `ListPages` (the tree in menu order) complete the set; they dispatch `PageCreated`, `PageUpdated`, `PageMoved`, `PageDeleted` and `PageRestored`.

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

`key()` is stored with every strip, so it never changes once content uses it, also not when you rename the class. It uses lowercase letters, digits and dashes. Optionally override `label()` (the name in the catalogue, by default the class name), `description()`, `view()` (the Blade view, by default `strips.{key}`) and `with(array $values)` (extra data to render with, such as the latest news). `with()` receives the rendered values: every field, links as urls. Its keys land next to the fields and override a field with the same name, never `id` or `type`.

| Field | Stored value | Rendered value | Notes |
|---|---|---|---|
| `Text::make('kop')` | string | string | One line, at most 255 characters. Print it escaped. |
| `LongText::make('intro')` | string | string | Plain text over several lines. Print it escaped, e.g. `{!! nl2br(e($strip->intro)) !!}`. |
| `RichText::make('tekst')` | HTML | HTML | Only `p`, `strong`, `em`, `ul`, `ol`, `li` and `a href` (http, https, mailto, tel, relative). Other markup keeps its text but loses its tags; scripts, styles and embeds disappear. Cleaned when it is stored, so print it unescaped. |
| `Link::make('gaat_naar')` | `{"page": 12}` or `{"url": "…"}` | url or `null` | A page of the site, which keeps working when its path changes, or an address: `https://`, `mailto:`, `tel:`, `/path` or `#anchor`. A page renders as its current path, `null` when it is gone or not published. |
| `Button::make('knop')` | `{"text": "…", "link": {…}}` | `{"text": "…", "url": "…"}` or `null` | A text and a link; `null` without a text or a url, so a view shows a button or nothing. |
| `Choice::make('stijl', $options)` | string | string | Options as a list, as `value => label`, or a backed enum (labels from its `label()` method when it has one). |
| `Items::make('knoppen', [...])` | list of items | list of items | Every item has the given fields; `min()`/`max()` limit the count, `required()` asks for at least one. One level deep. Rendered, it is always a list and every item has every field. |

Every field takes `label()` (by default the key as a sentence: `gaat_naar` becomes "Gaat naar"), `hint()`, `required()` and `default()`. Fields are immutable: each of these returns a changed copy.

The field names `id`, `type` and `visible` are reserved for the strip itself. Every definition is checked when the catalogue is built; a mistake (an invalid or duplicate key, a reserved field, an invalid `min`/`max`, a choice without options) throws an exception that names the strip type.

Read the catalogue through `Made\CmsPackage\Content\Strips\StripTypes`: `app(StripTypes::class)->all()`, `->has('hero')` and `->find('hero')`. A strip type derives its validation rules (`rules()`, with `attributes()` for the messages), the values of a new strip (`defaults()`), the stored form of validated values (`normalize()`) and the rendered values (`render()`, every field in order, a missing one from its default, with `with()` on top).

Config keys: `strips.path` (default `app_path('Cms/Strips')`) and `strips.namespace` (default `App\Cms\Strips`).

## Serving pages

Every page lives at an address of the site (`/` for the home page, `/over-ons/team` for a nested one). Serve the pages with one line, as the **last** line of `routes/web.php`:

```php
use Made\CmsPackage\Content\Actions\RenderStrips;
use Made\CmsPackage\Content\Models\Page;

// Blade
Route::madeCms(fn (Page $page) => view('page', ['page' => $page]));

// Inertia (React or Vue)
Route::madeCms(fn (Page $page) => Inertia::render('Page', [
    'title' => $page->title,
    'strips' => RenderStrips::run($page)->toArray(),
]));

// or an invokable class of your own
Route::madeCms(ShowPage::class);
```

The macro registers a `GET` route for every address that no route above it claims. It looks up the published page at that address and hands it to your responder, which returns the response in whatever stack the site uses. An address without a page, and a page that is not published, answer 404 to visitors; editors see drafts in the [edit mode](#edit-mode). Render the strips of the page as described in [Rendering strips](#rendering-strips); `$page->strips` holds them only as stored.

- Routes of the site above the line win; routes below it are never reached. A `Route::fallback()` of the site is no longer reached for `GET` requests: an address without a page gets the 404 page of the site.
- It takes the prefix and middleware of a surrounding group: `Route::prefix('nl')->group(fn () => Route::madeCms(...))` serves the pages under `/nl`. Call it on `Route::` directly or inside `->group()`; `Route::middleware('x')->madeCms()` does not work.
- It returns the route without a name, so you can chain `->name('page')` or `->middleware(...)` and use the macro more than once. It works with `php artisan route:cache`.
- The route parameter is called `path`. Call the parameter of your responder anything else (`$page`), or implicit route binding looks the page up by id and answers 404.
- A `POST` to an address without a route answers 405 instead of 404, like any catch-all route.
- Every page has one address. A `GET` or `HEAD` for another form of it (`/Over-Ons`, `/over-ons/`, `//over-ons`) answers a `301` to the canonical address `/over-ons`, under the prefix of the group (`/nl/over-ons`) and with the query string. An address without a page answers 404, not a redirect. Laravel's `.htaccess` already redirected a trailing slash on Apache; now nginx and Herd do the same. `/index.php/over-ons` is left to the web server.

The address of a page is in `$page->route->path`. Addresses live in their own table (`cms_routes`), unique across the site; a page that is not published keeps its address. An address is lowercase, without a trailing slash: `/Over-Ons/` redirects to `/over-ons`. Store a path through `PagePath::fromString($path)->value`, so it matches what visitors ask for. Find the page a visitor sees with `FindPublishedPage::run(PagePath::fromString('/over-ons'))`, or query with `Page::query()->published()->atPath(PagePath::fromString('/over-ons'))`. `FindPage::run($path)` finds the page at an address whatever its status.

### Edit mode

The CMS edits a page with the site in an iframe on the same origin, at the address of the page with `?made-cms=edit`. For a logged-in editor who opens the pages screen, the same route then serves the edit mode:

- your responder gets the page whatever its status, so also a page that was never published;
- `RenderStrips::run($page)` and `<x-made-cms::strips>` render the working copy: the draft, or the published strips when there is no draft. Hidden strips stay out;
- the response is sent with `Cache-Control: no-store, private`, `X-Frame-Options: SAMEORIGIN` and `X-Robots-Tag: noindex, nofollow`, and gets no launcher, because the CMS has a bar of its own.

For a visitor, a guest or any other value the parameter changes nothing: a draft still answers 404. Your responder needs no code for the edit mode, but keep in mind:

- The macro has to sit in the `web` group: without the session the editor is unknown and the parameter is ignored.
- In the edit mode `$page->status` can be a draft and `$page->published_at` can be `null`; a view that prints the date must cope with that.
- A link to a page that is not published renders as `null` in the edit mode too, as it does for visitors.
- The page has to be allowed in a frame of the same origin. The edit mode sets its headers inside the route, so headers from middleware in the `web` group, the global middleware or the web server come on top of them. If the site sends `X-Frame-Options`, use `SAMEORIGIN` instead of `DENY`. With a Content Security Policy, allow `frame-ancestors 'self'` instead of `'none'`. For the public site both are as safe as forbidding every frame, because only the site itself can frame it.
- Do not let a full-page cache (a CDN rule, `spatie/laravel-responsecache`) store or serve responses with `?made-cms=edit`. Cache headers chained on the route itself (`Route::madeCms(...)->middleware('cache.headers:…')`) are replaced by the edit mode.

### Edit shell

On a page served by `Route::madeCms()`, the edit bar of a logged-in editor who opens the pages screen links to "Bewerken": the edit shell of the CMS at `/made-cms/pages/{page}/edit` (route `made-cms-package.pages.edit`). It shows the page in the edit mode in a frame, with a bar on top and a side panel on the right. Every element with `data-cms-strip` gets an outline with the name of its strip type and its number of fields; clicking one chooses that strip, also after the frame reloads. The outlines sit in a shadow root of their own, so the css of the site needs nothing for them.

- A visible strip of the page whose view has no `data-cms-strip` on its root cannot be chosen; the shell warns in the browser console which strip and strip type it is.
- Strips that the site renders in the browser after the page loaded (React or Vue without SSR) get their outline as soon as they appear.
- Links and forms of the site do nothing inside the frame, so the frame stays on the page in the edit mode.


## Rendering strips

The site renders the strips itself, in its own stack; the package hands over the data, ready to render. `RenderStrips::run($page)` gives the strips a visitor sees: the published version (the draft in the [edit mode](#edit-mode)), in the order of the page, without hidden strips and without strips of a type that is no longer in the catalogue. `->toArray()` gives a list in which every strip is its `id`, its `type` and its rendered fields:

```php
[
    ['id' => '9b1c…', 'type' => 'hero', 'label' => null, 'kop' => 'Welkom', 'intro' => '<p>…</p>', 'knoppen' => [['tekst' => 'Lid worden', 'gaat_naar' => '/lid-worden', 'stijl' => 'primary']]],
    ['id' => '4f0a…', 'type' => 'cards', 'kop' => 'Trainingen', 'kaarten' => [/* … */], 'aantal' => 3],
]
```

A link to a page is the path of its address in `cms_routes` (`/over-ons`), without the prefix of a surrounding route group: a site under `/nl` puts that in front itself.

### Blade

```blade
{{-- resources/views/page.blade.php --}}
<x-made-cms::strips :page="$page" />
```

The component renders every strip with the view of its strip type, `strips.{key}` (`resources/views/strips/hero.blade.php`) or the name `view()` returns, and passes the strip as `$strip`. Read its values as properties; an unknown name is `null`. Print text escaped and rich text unescaped:

```blade
{{-- resources/views/strips/hero.blade.php --}}
<section data-cms-strip="{{ $strip->id }}">
    <h1 data-cms-field="kop">{{ $strip->kop }}</h1>
    <div data-cms-field="intro">{!! $strip->intro !!}</div>

    @foreach ($strip->knoppen as $knop)
        <a href="{{ $knop['gaat_naar'] }}">{{ $knop['tekst'] }}</a>
    @endforeach
</section>
```

The component looks up the view of every strip before it renders the first one. A missing view throws `MissingStripView`, which names the strip type, its class and the view it looked for.

### React and Vue (Inertia)

Hand `RenderStrips::run($page)->toArray()` to the page as a prop (see [Serving pages](#serving-pages)) and map every `type` to a component of your own:

```tsx
const types = { hero: Hero, cards: Cards };

page.strips.map((s) => { const C = types[s.type]; return <C key={s.id} strip={s} />; });

function Hero({ strip }) {
  return (
    <section data-cms-strip={strip.id}>
      <h1 data-cms-field="kop">{strip.kop}</h1>
      <div data-cms-field="intro" dangerouslySetInnerHTML={{ __html: strip.intro ?? '' }} />
    </section>
  );
}
```

```vue
<component v-for="s in page.strips" :key="s.id" :is="types[s.type]" :strip="s" />

<!-- Hero.vue -->
<section :data-cms-strip="strip.id">
  <h1 data-cms-field="kop">{{ strip.kop }}</h1>
  <div data-cms-field="intro" v-html="strip.intro" />
</section>
```

### The data-cms attributes

- `data-cms-strip` with the id of the strip on the root element of every strip is required. It is the only dynamic value: the [edit shell](#edit-shell) finds the strip by it.
- `data-cms-field="kop"` on the element that shows a field, and `data-cms-items="knoppen"` on the element around a list, are optional and static. With them an editor sees a change while typing; without them after the page renders again.

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
│   ├── Actions/    e.g. RenderStrips, the strips of a page ready to render
│   └── ...
├── Console/    technical layer: artisan commands
├── Http/       technical layer: middleware (the launcher, page lookup), the Route::madeCms() macro, the <x-made-cms::strips> component, controllers grouped per context, the login session
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
