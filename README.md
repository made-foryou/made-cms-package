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

## Requirements

- PHP 8.4 or higher with the extensions `dom` and `fileinfo`.
- Laravel 12 or 13.
- For uploaded photos: GD with WebP support (the default) or Imagick, and `exif` so photos from a phone come out upright.
- A `memory_limit` of at least 256 MB: with GD, scaling and cropping a phone photo of 12 MP (4032 × 3024 px) takes about 140 MB, and one of 24 MP up to about 290 MB.
- An `upload_max_filesize` and a `post_max_size` of at least `media.max_upload_kb` (25 MB by default), so PHP lets an upload through to the media library.
- A disk for the media library that gives a url: by default `public` (run `php artisan storage:link`), or an S3 disk such as DigitalOcean Spaces. See [Media](#media).

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

Do the same after every update of the package: a change of the database always comes as a new migration, so the ones you ran stay as they are and `migrate` runs only what is new.

A site that already had pages before it got the table `cms_media_usages` runs `php artisan made-cms:media-usage` once after migrating, so the media library knows where its files are used (see [Where a file is used](#where-a-file-is-used)). A site that already had photos before the library made thumbnails runs `php artisan made-cms:media-thumbnails` once (see [Thumbnails](#thumbnails)).

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
UpdateStrip::run($page, $strip->id, ['kop' => 'Welkom']);    // only the fields you pass change
MoveStrip::run($page, $strip->id, 2);
SetStripVisibility::run($page, $strip->id, false);
RemoveStrip::run($page, $strip->id);

PublishPage::run($page);   // the draft becomes the published version
DiscardDraft::run($page);  // or: back to the published version

Page::query()->published()->get();
Page::query()->recentlyEdited()->limit(3)->get();
```

`UpdateStrip` merges the values you pass with the stored values of the strip, so a seeder or an import can change one field without repeating the others. A field you leave out keeps its value; pass `null` (or `[]` for a list) to clear it. A list replaces the stored list, in the order you pass it, but every stored item has an `id` and an item you pass with that id keeps the fields you leave out. An item without a known id is new and gets an id. The merged values are checked against the strip type as a whole and refused with a `ValidationException` keyed by field:

```php
$strip = UpdateStrip::run($page, $strip->id, ['knoppen' => [
    ['tekst' => 'Lid worden', 'gaat_naar' => ['url' => '/lid-worden']],
]]);
$button = $strip->values['knoppen'][0]['id'];

UpdateStrip::run($page, $strip->id, ['knoppen' => [
    ['id' => $button, 'tekst' => 'Word lid'],                        // keeps its link
    ['tekst' => 'Contact', 'gaat_naar' => ['url' => '/contact']],    // a new button
]]);
```

Changes to the draft made at the same time never overwrite each other: every strip action, `PublishPage` and `DiscardDraft` reads the page again in a transaction with its row locked, so the next change waits and starts from what the previous one stored. The `$page` you pass holds the stored state afterwards. On MySQL and PostgreSQL that is all; SQLite ignores the row lock, so there a change that collides with another write fails with "database is locked" instead of waiting. From Laravel 12.26 `'transaction_mode' => 'IMMEDIATE'` on the SQLite connection makes it wait.

Every write dispatches an event that your application can listen to: `EditorCreated`, `EditorRolesChanged`, `LoginCodeIssued`, `EditorLoggedIn`, `EditorLoggedOut`, `TwoFactorEnabled`, `StripAdded`, `StripMoved`, `StripRemoved`, `StripUpdated`, `StripVisibilityChanged`, `DraftDiscarded` and `PagePublished`. The events of the content wait for the commit of the transaction they are dispatched in, also one of your own around an action, and never reach a listener when it rolls back.

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

Every screen has its own route, `/made-cms/<screen>` (named `made-cms-package.screens.<screen>`, e.g. `/made-cms/redirects`). The pages screen and the media library are built; the other screens show that they are coming until they are. The routes are guarded by the `EnsureScreenAccess` middleware: a guest is sent to the login page, an editor without a role for the screen gets a 403.

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
- **One page** (`/made-cms/pages/{page}`) changes the name, the address, the parent and whether the page is in the menu. The pages under it move along, and their addresses follow. The switch says the place of the page in the menu of the site, counting only what the site shows there: a draft says where it comes once published.
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

### The menu of the site

Build the menu of the site from `ListMenuPages`: the published pages with "Staat in het menu" on, in the order of the pages screen. A draft is left out with the pages under it, like a page outside the menu, and a deleted page never shows.

```php
use Made\CmsPackage\Content\Actions\ListMenuPages;

foreach (ListMenuPages::run()->roots as $item) { // ->roots for one level, $item->children for a submenu
    echo $item->title, ' ', $item->path?->value;
}
```

`ListPages::run()->inMenu()` follows only the switch and keeps drafts: it is the tree of the CMS, not the menu of the site.

## Strip types

Editors build pages from strips: a hero, a row of cards, opening hours. The site decides which kinds of strips exist, one PHP class per strip type in `app/Cms/Strips`. The package finds every class in that folder and its sub-folders and offers them as the catalogue editors pick from.

```php
namespace App\Cms\Strips;

use App\Enums\ButtonStyle;
use Made\CmsPackage\Content\Strips\Fields\Choice;
use Made\CmsPackage\Content\Strips\Fields\Heading;
use Made\CmsPackage\Content\Strips\Fields\Image;
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
            Heading::make('kop')->tag('h1')->required(),
            RichText::make('intro'),
            Image::make('foto')->label('Achtergrondfoto')->hint('Minimaal 1600 px breed.'),
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
| `Text::make('kop')` | string | string | One line, at most 255 characters. Print it escaped. With `->emphasis()` an editor marks words with asterisks; print it with `<x-made-cms::emphasis>`, see [Emphasis](#emphasis). |
| `LongText::make('intro')` | string | string | Plain text over several lines. Print it escaped, e.g. `{!! nl2br(e($strip->intro)) !!}`. |
| `RichText::make('tekst')` | HTML | HTML | Only `p`, `strong`, `em`, `ul`, `ol`, `li` and `a href` (http, https, mailto, tel, relative, but not `//`). Other markup keeps its text but loses its tags; scripts, styles and embeds disappear. Cleaned when it is stored and again when it is rendered, so print it unescaped. |
| `Link::make('gaat_naar')` | `{"page": 12}` or `{"url": "…"}` | url or `null` | A page of the site, which keeps working when its path changes, or an address: `https://`, `mailto:`, `tel:`, `/path` or `#anchor`. A page renders as its current path, `null` when it is gone or not published; an address the validation would refuse, e.g. from a seeder, renders as `null` too. |
| `Button::make('knop')` | `{"text": "…", "link": {…}}` | `{"text": "…", "url": "…"}` or `null` | A text and a link; `null` without a text or a url, so a view shows a button or nothing. |
| `Heading::make('kop')` | `{"text": "…", "tag": "h2"}` | `{"text": "…", "tag": "h2"}` or `null` | A text (as a `Text`) with its level: `h1` to `h6`, or `span` for a text that is no heading for search engines. `->tag('h1')` gives the level of the design (`h2` by default), `->tags(['h1', 'h2', 'h3'])` limits the levels to choose from (all seven by default). A text stored before the field became a `Heading`, and a level no longer in `tags()`, count as the default level. `null` without a text. `->emphasis()` as for a `Text`. See [Headings](#headings). |
| `Image::make('foto')` | the id of a file of the [media library](#media) | `{"url": "…", "alt": "…", "width": 1600, "height": 1067, "focal": {"x": 0.5, "y": 0.5}}` or `null` | An image of the library, so a file changes everywhere it is used. The url comes from the media disk, never the place on the disk; the alt text is the one of the file (an empty text without one); an svg has no `width` and `height`; without a focal point it is the centre. `null` without an image and when its file is gone or is no image, so the site shows its own fallback; saving or publishing the strip refuses such a file. No `default()`, also not in the default of a list: the id of a file differs per database. See [Images](#images). |
| `Choice::make('stijl', $options)` | string | string | Options as a list, as `value => label`, or a backed enum (labels from its `label()` method when it has one). |
| `Items::make('knoppen', [...])` | list of items, each with an `id` | list of items, each with an `id` | Every item has the given fields; `min()`/`max()` limit the count, `required()` asks for at least one, `itemLabel('Knop')` names one item in the side panel ("Knop toevoegen", "Item" by default). One level deep. Every stored item gets a fixed `id` (a uuid) that stays the same when the list changes, so use it as the key of a list in React or Vue. Rendered, it is always a list and every item has its `id` and every field. |

Every field takes `label()` (by default the key as a sentence: `gaat_naar` becomes "Gaat naar"), `hint()`, `required()` and `default()`. Fields are immutable: each of these returns a changed copy.

The field names `id`, `type` and `visible` are reserved for the strip itself, and `id` inside an item. Every definition is checked when the catalogue is built; a mistake (an invalid or duplicate key, a reserved field, an invalid `min`/`max`, a choice without options, a heading level that is no tag or a default level outside `tags()`, an image with a default) throws an exception that names the strip type.

Read the catalogue through `Made\CmsPackage\Content\Strips\StripTypes`: `app(StripTypes::class)->all()`, `->has('hero')` and `->find('hero')`. A strip type derives its validation rules (`rules()`, with `attributes()` for the labels and `messages()` for Dutch and English messages of the package, which take the place of the host's `validation.*` lines), the values of a new strip (`defaults()`), the stored form of validated values (`normalize()`), the rendered values (`render($values, $media)`, every field in order, a missing one from its default, images from the files `FindMedia` found, with `with()` on top), the files of the media library the strip uses (`media()`, and by the path of their place `places()`, named by `placeLabels()`), how new values combine with stored ones (`merge()`, which `UpdateStrip` uses) and the description the side panel builds its controls from (`describe()`: the key, name and fields, each field with its `type()`, label, hint, default and what its type adds).

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

`?made-cms=preview` serves the same outside the frame: "Voorvertoning" in the edit shell opens the page with its draft in a tab of its own, without anything of the CMS on it. A link on that page leads to what visitors see.

For a visitor, a guest or any other value the parameter changes nothing: a draft still answers 404. Your responder needs no code for the edit mode, but keep in mind:

- The macro has to sit in the `web` group: without the session the editor is unknown and the parameter is ignored.
- In the edit mode `$page->status` can be a draft and `$page->published_at` can be `null`; a view that prints the date must cope with that.
- A link to a page that is not published renders as `null` in the edit mode too, as it does for visitors.
- The page has to be allowed in a frame of the same origin. The edit mode sets its headers inside the route, so headers from middleware in the `web` group, the global middleware or the web server come on top of them. If the site sends `X-Frame-Options`, use `SAMEORIGIN` instead of `DENY`. With a Content Security Policy, allow `frame-ancestors 'self'` instead of `'none'`. For the public site both are as safe as forbidding every frame, because only the site itself can frame it.
- Do not let a full-page cache (a CDN rule, `spatie/laravel-responsecache`) store or serve responses with `?made-cms=edit` or `?made-cms=preview`. Cache headers chained on the route itself (`Route::madeCms(...)->middleware('cache.headers:…')`) are replaced by the edit mode.

### Edit shell

On a page served by `Route::madeCms()`, the edit bar of a logged-in editor who opens the pages screen links to "Bewerken": the edit shell of the CMS at `/made-cms/pages/{page}/edit` (route `made-cms-package.pages.edit`). It shows the page in the edit mode in a frame, with a bar on top and a side panel on the right. Every element with `data-cms-strip` gets an outline with the name of its strip type and its number of fields; clicking one chooses that strip, also after the frame reloads. The outlines sit in a shadow root of their own, so the css of the site needs nothing for them.

- A visible strip of the page whose view has no `data-cms-strip` on its root cannot be chosen; the shell warns in the browser console which strip and strip type it is.
- Strips that the site renders in the browser after the page loaded (React or Vue without SSR) get their outline as soon as they appear.
- Links and forms of the site do nothing inside the frame, so the frame stays on the page in the edit mode.
- A field marked with [`data-cms-field`](#the-data-cms-attributes) changes on the site while the editor types; anything else shows once the draft is saved and the frame reloads at the same scroll position, with the same strip chosen.
- Clicking a field on the site opens it in the side panel with the focus in it, inside its item when it lies in a list. The tab with the name of the strip only chooses the strip.
- An `Image` field is a media element: the photo with its extension, focal point and an amber dot when it has no alt text, its name, "1920 × 1080 · 412 kB · uit map Teams" and its alt text; "Nog geen afbeelding" without one, or that its file is no longer in Media. "Kiezen uit media" opens the picker with the images of the media library (see [Choosing from the library](#choosing-from-the-library)); "Uploaden" opens the upload field below it for one photo, which goes into the folder the editor opened last in the picker ("Alle bestanden" until then) and is the value of the field as soon as it is in; the editor gives it its alt text right there. "Bijsnijden" opens the crop step for the photo (see [Cropping](#cropping)); it changes the file, so the photo changes on every page and the frame reloads, while the field keeps its value. "×" takes the photo out of the field, the file stays in Media.
- While a photo travels, "Annuleren" stops it. An upload runs on while the editor works on another strip; a line at the foot of the side panel says how it goes, that the photo is in, or that it failed with a button to that field. "Publiceren" waits for a photo that is still on its way.

The bar shows whether the page is live and what its draft changes:

- "Concept · 2 wijzigingen" counts the strips the draft adds, changes (fields or visibility), removes or moves compared to the published page (`CompareDraft`). One strip dragged to another place is one change; a field left out counts as its default. Without changes it says "Geen wijzigingen", or "Nog niet gepubliceerd" for a page that never went live.
- "Publiceren" first saves what the side panel still holds, then puts the draft live (`PublishPage`) and reloads the frame. When a strip breaks its definition, for instance after a new required field, nothing is published: the bar names the strip with its first message and the panel shows the errors at its fields.
- "Concept weggooien" appears once the draft changes something. It asks to confirm, throws the draft away (`DiscardDraft`) and reloads the shell with the published strips.
- "Voorvertoning" opens the page with `?made-cms=preview` in a new tab.
- Every editor who opens the pages screen may publish and discard.

Without a chosen strip the side panel shows the opbouw of the page: every strip in its order, with the name of its strip type, a few words of its content (the first field with text, else the titles of the items of a list, `StripType::summary()`) and its number of fields. Editors build the page freely from the strip types of the site:

- "+ Strook toevoegen" at the end, or "+ Strook hiertussen" between two strips, opens the catalogue: every strip type with its `label()`, its `description()` and its number of fields, sorted by label, with a search field once the site has 10 types or more. One click places a new strip of that type with its defaults (`AddStrip`); the frame reloads with the new strip chosen and its fields open, and a note names the required fields that are still empty. Publishing waits for those.
- A strip moves by dragging its grip, by focusing the grip and pressing ↑ or ↓ (Enter drops it, Escape puts it back) or with the arrows next to its name once it is chosen (`MoveStrip`). The frame reloads with the strips in their new order.
- "Strook verwijderen" under the fields of a chosen strip takes it off the page without asking (`RemoveStrip`). A notice at the top of the opbouw puts it back with "Ongedaan maken": the same strip with its content, in its place (`RestoreStrip`), so the bar counts nothing for it. What the editor typed in it is saved before it goes. The notices stay until the strip is put back, the draft is published or thrown away.
- A hidden strip stays in the opbouw with "Verborgen"; "Bewerken" chooses it, also when it is not on the site.
- A strip of a type that is no longer in the catalogue stays in the opbouw as "Niet op de site": its content is kept, it cannot be chosen or moved and it can only be removed.
- "Strook 2 van 6", in the panel and on the outlines in the frame, is the place of a strip in the opbouw: hidden strips and strips of a type the site no longer has count too.

An editor who opens Meta-overzicht (the SEO specialist and the admin) switches with a tab between the opbouw and "Koppen": every heading of the page as the frame shows it, in order and indented by level, so it also sees the headings of the layout and of a site that renders in the browser.

- A heading of a field of a strip shows the name of the strip ("Kaarten › Kaarten" in a list) and opens that field, in its item when it lies in a list; "← Koppen" goes back. A heading field at "Geen kop" is listed as `span`, so it can become a heading again.
- A heading the site or a strip view prints itself is fixed ("vast in de site", "vast in de strook"): it is listed dimmed and cannot be clicked. A `span` of the site is never listed.
- A heading the site hides with css stays in the list with "niet zichtbaar op het scherm" and counts in the signals, because search engines read it. A hidden strip is not on the site, so its headings are not listed.
- Above the list the overview signals a page without an H1, more than one H1, a skipped level (an H4 right after an H2) and an empty heading (an h1–h6 without a text; a `Heading` field without a text renders nothing). A signal on a fixed heading asks to have the developer of the site change it.
- The overview follows the frame: after a level is chosen the frame reloads and the list follows.

Choosing a strip shows its fields, built from its strip type, with the values of the draft:

| Field | Control |
|---|---|
| `Text` | One line. With `->emphasis()`, a line below it says how to give a word emphasis: "Zet een woord tussen sterretjes voor nadruk, zoals \*handen\*." |
| `LongText` | A text area. |
| `RichText` | A text with a fixed toolbar: bold, italic, link and list. Pasted text arrives without its markup; the package cleans the HTML again when it stores it. |
| `Link` | One input that suggests the pages of the site. A chosen page, or the exact address of a page, is stored as that page and follows it when its address changes; anything else is stored as an address. |
| `Button` | Its text and its link side by side. |
| `Heading` | Its text, with its level next to its name. An editor who opens Meta-overzicht (the SEO specialist and the admin) chooses the level from the levels of the field, each with what it means; anyone else sees the level as a label, and saving keeps it for them. With `->emphasis()` the line about emphasis stands below its text, as for a `Text`. |
| `Choice` | A select with the options in their order; "Geen keuze" when the field is not required. |
| `Items` | A row per item with its title (the first field that has a value, without the asterisks of its emphasis), a grip and "Bewerken". The grip drags the item, or moves it with the arrow keys. "Bewerken" opens the item with a breadcrumb ("← Hero / Knoppen / Lid worden"); adding stops at `max()`, removing at `min()`. |

- Every change is saved as the draft: a moment after the editor stops typing, when a field loses focus, when another strip is chosen and with "Opslaan als concept". The panel always sends every field of the strip to `UpdateStrip`. A field with [`data-cms-field`](#the-data-cms-attributes) already shows on the site while the editor types; for any other change the frame reloads at the same scroll position. Visitors keep seeing the published version until the page is published.
- A value the strip type refuses is not saved. Its message appears under the field once the editor leaves it (or presses "Opslaan als concept"), and the panel says how many fields still need a fix. The shell warns before it is closed with changes that were not saved.
- "Strook zichtbaar op de site" shows or hides the strip (`SetStripVisibility`). A hidden strip is not on the site in the edit mode either; the opbouw lists it so the editor can choose it and show it again.
- "Ongedaan maken" takes the last saved change of the chosen strip back, one step per click: its fields or its visibility. Something typed and not saved yet goes back to what is stored. The steps last as long as the shell is open.
- The panel, the opbouw and the bar talk JSON, under the same `Screen::Pages` access as the shell. The host adds nothing for them.

| Endpoint | Route `made-cms-package.…` | Does |
|---|---|---|
| `GET /made-cms/pages/{page}/strips` | `pages.strips.index` | The strips of the working copy in their stored order, with their counts. |
| `POST /made-cms/pages/{page}/strips` | `pages.strips.store` | Adds a strip: `{type, position}`. |
| `GET`/`PUT …/strips/{strip}` | `pages.strips.show`, `.update` | The fields and values of a strip; saves every field: `{values}`. |
| `PUT …/strips/{strip}/visibility` | `pages.strips.visibility` | Shows or hides a strip: `{visible}`. |
| `PUT …/strips/{strip}/position` | `pages.strips.position` | Moves a strip: `{position}`, its place counted from 0. |
| `DELETE …/strips/{strip}` | `pages.strips.destroy` | Removes a strip and answers it as it was stored. |
| `POST …/strips/{strip}/restore` | `pages.strips.restore` | Puts a removed strip back: `{type, visible, values, position}`. |
| `GET`/`DELETE /made-cms/pages/{page}/draft` | `pages.draft.show`, `.destroy` | The state of the draft; throws it away. |
| `POST /made-cms/pages/{page}/publish` | `pages.publish` | Puts the draft live. |

  A position is the place of a strip among all strips of the draft, also hidden ones and strips of a type that left the catalogue. Every answer of the strip endpoints that changes the order holds the whole list again.


## Rendering strips

The site renders the strips itself, in its own stack; the package hands over the data, ready to render. `RenderStrips::run($page)` gives the strips a visitor sees: the published version (the draft in the [edit mode](#edit-mode)), in the order of the page, without hidden strips and without strips of a type that is no longer in the catalogue. `->toArray()` gives a list in which every strip is its `id`, its `type` and its rendered fields:

```php
[
    ['id' => '9b1c…', 'type' => 'hero', 'label' => null, 'kop' => ['text' => 'Welkom', 'tag' => 'h1'], 'intro' => '<p>…</p>', 'foto' => ['url' => 'https://…/storage/media/01k9…/teamfoto.webp', 'alt' => 'Het eerste team', 'width' => 1600, 'height' => 1067, 'focal' => ['x' => 0.5, 'y' => 0.5]], 'knoppen' => [['tekst' => 'Lid worden', 'gaat_naar' => '/lid-worden', 'stijl' => 'primary']]],
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
    <x-made-cms::heading :heading="$strip->kop" field="kop" class="hero__kop" />
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
  const Kop = strip.kop?.tag;

  return (
    <section data-cms-strip={strip.id}>
      {Kop && <Kop data-cms-field="kop" className="hero__kop">{strip.kop.text}</Kop>}
      <div data-cms-field="intro" dangerouslySetInnerHTML={{ __html: strip.intro ?? '' }} />
    </section>
  );
}
```

```vue
<component v-for="s in page.strips" :key="s.id" :is="types[s.type]" :strip="s" />

<!-- Hero.vue -->
<section :data-cms-strip="strip.id">
  <component v-if="strip.kop" :is="strip.kop.tag" data-cms-field="kop" class="hero__kop">{{ strip.kop.text }}</component>
  <div data-cms-field="intro" v-html="strip.intro" />
</section>
```

### Headings

A `Heading` field holds the level of the heading next to its text, so the element the site prints follows the value: `h1` to `h6`, or `span` for a text that is no heading for search engines. Editors who open Meta-overzicht choose the level in the side panel while they see the page. Give a heading its look with a class, not with `h2 { … }`, because the level can change while the heading should keep looking the same.

- Blade: `<x-made-cms::heading :heading="$strip->kop" field="kop" class="hero__kop" />` renders `<h1 data-cms-field="kop" class="hero__kop">Welkom</h1>`, with the other attributes you give it and the text escaped, and nothing when the heading has no text. In a list `field` names the field of the item, as `data-cms-field` does: `<x-made-cms::heading :heading="$kaart['titel']" field="titel" class="kaart__titel" />`.
- React: `const Kop = strip.kop?.tag;` and `{Kop && <Kop data-cms-field="kop" className="hero__kop">{strip.kop.text}</Kop>}`.
- Vue: `<component v-if="strip.kop" :is="strip.kop.tag" data-cms-field="kop" class="hero__kop">{{ strip.kop.text }}</component>`.

A heading that was a `Text` switches without a migration: change `Text::make('kop')` into `Heading::make('kop')` with the level the view printed (`->tag('h1')`), and print the heading as above. A stored text counts as that text with the default level until an editor saves the strip; publishing and the count of changes treat both the same.

### Emphasis

A `Text` or `Heading` with `->emphasis()` lets an editor give words emphasis by putting them between asterisks: "Jullie dag in \*vertrouwde\* handen", also more than one piece or inside a word (`Privacy*verklaring*`). The side panel says how, and the edit shell shows the words as an `<em>` while the editor types. The value keeps the asterisks, stored and rendered (they count in the 255 characters), so the view prints the words between them as an `<em>` and escapes the rest:

- Blade: `<x-made-cms::heading :heading="$strip->kop" field="kop" emphasis class="hero__kop" />` for a heading. For a `Text`, put `<x-made-cms::emphasis>` in your own element: `<h2 data-cms-field="kop" class="hero__kop"><x-made-cms::emphasis :text="$strip->kop" /></h2>`. It prints nothing else, no whitespace either, and nothing without a text.
- React: split the text on the pattern below; the words with emphasis land at the odd places.

```tsx
const EMPHASIS = /\*([^\s*](?:[^*]*[^\s*])?)\*/;

function Emphasis({ text }: { text: string }) {
  return <>{text.split(EMPHASIS).map((part, i) => (i % 2 ? <em key={i}>{part}</em> : part))}</>;
}

{Kop && <Kop data-cms-field="kop" className="hero__kop"><Emphasis text={strip.kop.text} /></Kop>}
```

- Vue: `<template v-for="(part, i) in strip.kop.text.split(EMPHASIS)" :key="i"><em v-if="i % 2">{{ part }}</em><template v-else>{{ part }}</template></template>` inside the element of the field.

The words between asterisks start and end with a character that is no space or asterisk, so an asterisk without a partner, `2 * 3 * 4` or `* a *` stays an asterisk; without `->emphasis()` every asterisk is text. `Content\Strips\Emphasis::parts()` gives the parts of a text in PHP, `Emphasis::plain()` the text without the asterisks (for a `<title>` or an `aria-label`). Style the `<em>` through the class of its element (`.hero__kop em`; in Vue with scoped css `.hero__kop :deep(em)`): the edit shell makes a new `<em>` like one the site printed, with its attributes, or a plain one when the text had none yet.

### Images

An `Image` field stores the id of a file of the [media library](#media) and renders as `{url, alt, width, height, focal}`, so the site never deals with ids. `RenderStrips` finds the files of every image on the page in one query. Without an image, and when its file is gone from the library or is no image, the field is `null`: show a fallback of your own. The focal point (`x` and `y` from 0 to 1) is the place a crop keeps in view; use it as `object-position`.

```blade
@if ($strip->foto)
    <img src="{{ $strip->foto['url'] }}" alt="{{ $strip->foto['alt'] }}" width="{{ $strip->foto['width'] }}" height="{{ $strip->foto['height'] }}"
         style="object-fit: cover; object-position: {{ $strip->foto['focal']['x'] * 100 }}% {{ $strip->foto['focal']['y'] * 100 }}%" data-cms-field="foto">
@endif
```

```tsx
{strip.foto && (
  <img src={strip.foto.url} alt={strip.foto.alt} width={strip.foto.width ?? undefined} height={strip.foto.height ?? undefined}
       style={{ objectFit: 'cover', objectPosition: `${strip.foto.focal.x * 100}% ${strip.foto.focal.y * 100}%` }} data-cms-field="foto" />
)}
```

```vue
<img v-if="strip.foto" :src="strip.foto.url" :alt="strip.foto.alt" :width="strip.foto.width" :height="strip.foto.height"
     :style="{ objectFit: 'cover', objectPosition: `${strip.foto.focal.x * 100}% ${strip.foto.focal.y * 100}%` }" data-cms-field="foto" />
```

The alt text belongs to the file, so it is the same on every page; an empty one means the image has none yet. An svg has no `width` and `height` (`null`). With `data-cms-field` on the `<img>` the editor sees another image at once; a `srcset`, a `<picture>` or a background reloads the frame instead.

### The data-cms attributes

- `data-cms-strip` with the id of the strip on the root element of every strip is required. It is the only dynamic value: the [edit shell](#edit-shell) finds the strip by it.
- `data-cms-field="kop"` on the element that shows a field, and `data-cms-items="knoppen"` on the element around a list, are optional and static. With them the editor sees a change on the site while typing, and clicking the field on the site opens it in the side panel. Without them the frame reloads after the draft is saved, at the same scroll position.

The edit shell patches the element with `data-cms-field` in the page as it is, so the element has to hold the field the way the site prints it:

| Field | The element with `data-cms-field` |
|---|---|
| `Text` | Holds only the text: no icon or other element next to it. With emphasis it holds the text and its `<em>` elements, as `<x-made-cms::emphasis>` prints them. |
| `LongText` | Holds only the text. Print line breaks as `<br>` (`nl2br`) or keep them in the text (`white-space: pre-line`); the shell follows the form it finds. |
| `RichText` | Prints the HTML unescaped: `{!! !!}`, `dangerouslySetInnerHTML`, `v-html`. |
| `Link` | Has the address in its `href`. |
| `Button` | Holds only the text and has the address in its `href`. |
| `Heading` | Is the element of its level and holds only the text (with emphasis: and its `<em>` elements), as `<x-made-cms::heading>` prints it. An element of another level reloads the frame. |
| `Image` | Is the `<img>` that shows it, without `srcset` and not in a `<picture>`; the shell sets its `src` and `alt`, and its `width` and `height` when the site prints them. Another element (a background) reloads the frame. |
| `Choice`, `Items` | Not patched: the frame reloads. |

- In `data-cms-items="knoppen"` every direct child is one item, in the order of the list. A `data-cms-field` inside it names a field of that item (`titel`, not `kaarten.0.titel`) and may sit on the child itself. A view that leaves an item out shows fewer children than there are items; the shell then patches nothing in that list and reloads instead.
- An emptied field, a link to a page that is not published, an item that is added, removed or moved, and a strip that is shown or hidden reload the frame: they decide whether an element shows at all, or a default fills them in.
- The shell changes only the text node, `href`, HTML or the attributes of an image inside the element, never the element itself, so React and Vue keep the patch until the page renders again.

```blade
<ul data-cms-items="kaarten">
    @foreach ($strip->kaarten as $kaart)
        <li>
            <h3 data-cms-field="titel">{{ $kaart['titel'] }}</h3>
            <p data-cms-field="tekst">{!! nl2br(e($kaart['tekst'])) !!}</p>
            <a href="{{ $kaart['link'] }}" data-cms-field="link">Lees meer</a>
        </li>
    @endforeach
</ul>
```

```tsx
<ul data-cms-items="kaarten">
  {strip.kaarten.map((kaart) => (
    <li key={kaart.id}>
      <h3 data-cms-field="titel">{kaart.titel}</h3>
      <p data-cms-field="tekst" className="whitespace-pre-line">{kaart.tekst}</p>
      <a href={kaart.link} data-cms-field="link">Lees meer</a>
    </li>
  ))}
</ul>
```

```vue
<ul data-cms-items="kaarten">
  <li v-for="kaart in strip.kaarten" :key="kaart.id">
    <h3 data-cms-field="titel">{{ kaart.titel }}</h3>
    <p data-cms-field="tekst" style="white-space: pre-line">{{ kaart.tekst }}</p>
    <a :href="kaart.link" data-cms-field="link">Lees meer</a>
  </li>
</ul>
```

## Media

The media library holds every photo, document and video of the site in one place. A strip stores the id of a file, so a file changes everywhere it is used, and its alt text belongs to the file: written once, it holds on every page.

Files go to the disk in `media.disk` (env `MADE_CMS_MEDIA_DISK`, default `public`). A visitor must be able to open them by url, so use the `public` disk after `php artisan storage:link`, a local disk with a `url`, or an S3 disk such as DigitalOcean Spaces (see [DigitalOcean Spaces and other S3 disks](#digitalocean-spaces-and-other-s3-disks)). A local disk without a `url` is refused with a `MediaDiskNotUsable` exception the first time the library is used; the site and artisan keep working until then.

The library writes every file with a visibility of its own, whatever the `visibility` of the disk: the photo, its thumbnail and a file kept as it came in are public, the original of a photo is private, and so is a former version that waits to be put back after a replacement. On a local disk that is 0644 for a public file and 0600 for the original, in folders of 0755. PHP still reads the original to crop the photo again; a web server that runs as another user than PHP cannot serve it. Where the web server runs as the same user (Herd, `php artisan serve`, many servers with one user), it could, and the name of 40 random characters keeps the original from being found.

An upload is checked before anything is stored. The type is read from the contents, never from the name; a file larger than `media.max_upload_kb` (default 25 MB) or of a type outside `media.allowed_mimes` (jpg, png, webp, gif, svg, pdf and mp4) is refused with a message for the editor. Each file gets a folder of its own, `media/{ulid}/`:

- A photo (jpg, png, webp or a gif that does not move) is turned upright by its EXIF orientation, scaled down to at most `media.image.max_width` (1920 px, never up) and stored as `media.image.format` (webp) at `media.image.quality` (82), without the metadata of the camera. The upload is kept next to it under a name nobody can guess from the url of the photo, because it still holds the full size and that metadata; a later crop starts from it. A thumbnail for the tiles of the CMS goes next to them (see [Thumbnails](#thumbnails)).
- An svg is stored exactly as it came in, but only when it does nothing but draw. One with a script, an event attribute, html, an entity, a reference outside the drawing or a style that loads something is refused, because whoever opens its url would run it on the domain of your site.
- An animated gif, a pdf and an mp4 are stored exactly as they came in.

Photos are scaled in the request with [Intervention Image](https://image.intervention.io) and `media.image.driver` (env `MADE_CMS_IMAGE_DRIVER`: `gd`, the default, or `imagick`).

The Actions, for your own imports or seeders:

```php
use Made\CmsPackage\Content\Actions\UpdateAltText;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;

$media = UploadMedia::run(new UploadedMedia(
    path: storage_path('import/teamfoto.jpg'),   // a readable file on the server
    filename: 'Teamfoto Merwestad.jpg',          // becomes the name teamfoto-merwestad
    folderId: null,
    uploadedBy: null,                            // the id of the editor, when there is one
));

UpdateAltText::run($media, 'Het eerste team na de kampioenswedstrijd');
```

A photo can be cropped again from its original with `CropImage`, renamed with `RenameMedia` and moved with `MoveMedia` (see [Cropping](#cropping)). A file gets a new version, is put back and leaves the library with these Actions (see [Versions, downloading and deleting](#versions-downloading-and-deleting)):

```php
use Made\CmsPackage\Content\Actions\DeleteMedia;
use Made\CmsPackage\Content\Actions\ReplaceMedia;
use Made\CmsPackage\Content\Actions\RestoreMediaVersion;

// A new version under the same id: of the same kind, in a folder of its own, with the name of the upload.
$media = ReplaceMedia::run($media, new UploadedMedia(storage_path('import/teamfoto-2026.jpg'), 'Teamfoto 2026.jpg', null, $editorId));

// The former version waits; putting it back swaps the two, so doing it again undoes it.
$media = RestoreMediaVersion::run($media);

// Deletes what no page uses, every version with it, and keeps the rest.
$result = DeleteMedia::run([$media->id, $other->id]);
$result->deleted; // list<Media>
$result->kept;    // list<Media> a page uses
```

A refused file throws a `ValidationException` keyed by `file` (an alt text longer than 255 characters one keyed by `alt`). `UploadMedia` dispatches `MediaUploaded` and `UpdateAltText` dispatches `AltTextUpdated`, both after the commit. Read the library with `Media::query()->images()`, `->ofKind(MediaKind::Document)`, `->inFolder($folder)`, `->withoutAlt()`, `->search('merwestad')`, `->newestFirst()`, `->byName()` and `->countPerFolder()`, and the folders with `MediaFolder::query()->alphabetical()`. `ListMedia::run(new MediaFilter(...))` gives one page of the library (40 files, with the url a visitor opens each at) and `ListMediaFolders::run($kind)` the folders with the number of their files. A strip holds an image of the library through the field type `Image` (see [Images](#images)); `FindMedia::run($ids)` gives files with the url a visitor opens them at.

### DigitalOcean Spaces and other S3 disks

The library can keep its files on a DigitalOcean Space, so the storage grows apart from the app server, which only has to follow the traffic. It is an option: the `public` disk keeps working as it does. Install the S3 adapter of Flysystem:

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

Add a disk to `config/filesystems.php`, with `url` on the CDN address of the Space, and point the library at it:

```php
'spaces' => [
    'driver' => 's3',
    'key' => env('SPACES_KEY'),
    'secret' => env('SPACES_SECRET'),
    'region' => env('SPACES_REGION', 'ams3'),
    'bucket' => env('SPACES_BUCKET'),
    'endpoint' => env('SPACES_ENDPOINT', 'https://ams3.digitaloceanspaces.com'),
    'url' => env('SPACES_URL'), // the CDN address: https://{bucket}.ams3.cdn.digitaloceanspaces.com
    'throw' => false,
],
```

```dotenv
MADE_CMS_MEDIA_DISK=spaces
```

Keep File Listing of the Space off, so nobody lists the files of the library.

The library gives every file its ACL itself: `public-read` for the photo, its thumbnail and a file kept as it came in, `private` for the original of a photo, which then answers 403, through the CDN too. The visibility of the disk does not matter. Spaces supports these ACLs; an AWS bucket with ACLs disabled (Object Ownership "Bucket owner enforced") refuses every write that carries one, so it is not supported. Every file a visitor opens (the photo, its thumbnail, an svg, a gif, a pdf and an mp4) is written with `Cache-Control: public, max-age=31536000, immutable`: every crop, every new name and every new version writes at a new address, so a browser and the CDN may keep it for a year. The original gets no cache header. A replaced file is gone for a visitor at once, because its former version is made private, but a CDN may still serve a copy it holds until that expires: purge the CDN when a file must be gone everywhere at once. A wrong endpoint, bucket or key shows at the first upload, as a `MediaNotStored` exception (or the exception of Flysystem with `'throw' => true`).

What stays on the app server:

- Uploads go through the app server to the Space, never from the browser straight to the Space: the library reads the contents of every file before it stores it.
- Scaling down, cropping and making thumbnails run on the app server, in the request. Cropping a photo of 12 MP takes about 140 MB, so the `memory_limit` of 256 MB stays.
- For the crop step and for the crop itself the app server fetches the original from the Space.

With a Content Security Policy, allow the domain of the disk (the CDN address of the Space) in `img-src`, and in `media-src` when the site shows videos: the site and the screens of the CMS show the photos and thumbnails from there. The crop step needs no CORS on the Space, because it fetches the original through the app.

### Uploading in the CMS

Editors upload through one upload field, the same in the media library, the picker and the edit shell. The media library, the media element of the side panel and the picker show it, and every file it takes asks its alt text once the batch is in (for a document the description of its download link). Its endpoints:

| Request | Does | Answers |
|---|---|---|
| `POST /made-cms/media` | Takes one file (`file`, multipart) into the library, in the folder `folder` when given, through `UploadMedia`. | 201 with `{"file": …}`; 422 keyed by `file` (the message of the check) or `folder`. |
| `PUT /made-cms/media/{media}/alt` | Gives a file its alt text (`{"alt": "…"}`, blank takes it away) through `UpdateAltText`. | 200 with `{"file": …}`; 422 keyed by `alt`; 404. |
| `GET /made-cms/media/{media}/crop` | A photo as the crop step shows it, through `DescribeMediaCrop`. | 200 with `{"file", "folder", "original": {"url", "width", "height", "size", "extension"}, "crop", "scale_down", "pages", "line", "used"}`; 404 for a file without an original. |
| `GET /made-cms/media/{media}/original` | The whole original of a photo, upright, scaled down and without metadata, through `MakeOriginalPreview`; never stored by a cache. | 200 with the image; 422 keyed by `crop` when the original cannot be read; 404. |
| `PUT /made-cms/media/{media}/name` | Gives a file another name (`{"name": "…"}`, made a slug) through `RenameMedia`; its address changes with it. | 200 with `{"file": …}`; 422 keyed by `name`; 404. |
| `PUT /made-cms/media/{media}/move` | Puts a file into a folder (`{"folder": id}`, `null` for none) through `MoveMedia`. Only for an editor who opens the media library. | 200 with `{"file": …}`; 422 keyed by `folder`; 403; 404. |
| `POST /made-cms/media/{media}/crop` | Cuts a photo from its original (`{"crop": {"x", "y", "width", "height", "rotation", "flip"}, "focal": {"x", "y"}, "scale_down": true}`) through `CropImage`. | 200 with `{"file": …}`; 422 keyed by `crop`, `crop.*`, `focal.*` or `scale_down`; 404. |

`file` is the file as the CMS shows it: `{id, url, thumb, name, extension, meta, folder, folder_id, alt, image, kind, file, size}`, with `thumb` as the url to show it small at (see [Thumbnails](#thumbnails)), `extension` as the type of the file a visitor gets ("webp" for a photo that was scaled down), `meta` as "1920 × 1280 · 388 kB" and `image` as the site renders it; never its place on the disk. Both endpoints open for an editor who opens the pages or the media library, so every role uploads a photo while editing a page. A guest gets 401.

The field sends files one at a time, refuses a file larger than `media.max_upload_kb` before it travels (the server checks every file again) and keeps an error with its file while the others go on. PHP must let the upload through: with an `upload_max_filesize` or `post_max_size` below `media.max_upload_kb` the field tells the editor that the server is the limit.

A screen of the CMS includes the field with the partial `made-cms-package-launcher::cms.partials.upload` and `['upload' => UploadField::make($folder)]`, and loads `$upload['script']`. `UploadField::image($folder)` gives the field of an image: one photo at a time, only the image types of `media.allowed_mimes`, in the singular. The script also starts a field a screen puts on the page later. A screen hears what happens through events that bubble from the field: `made-cms:upload` `{busy, done, total, failed, percent, sending}`, `made-cms:uploaded` `{file}` and `made-cms:upload-closed` `{files}`; it opens the file chooser from a button of its own with `made-cms:upload-choose` (dispatched in the click of the editor) and hands the field the files of a drop zone of its own with `made-cms:upload-take` `{transfer}` (or `{files}`); it stops what is still on its way with `made-cms:upload-stop` on the field (a file the server is storing already lands in the library), and sends it into another folder with `made-cms:upload-folder` `{folder: {id, name}}` (`{folder: null}` for "Alle bestanden"): the files that come in from then on go there, a file in the field already keeps its folder. A screen that leaves the field (a dialog that closes) finishes it first with `made-cms:upload-finish` `{wait}`: a typed alt text is stored, without one it is "later", and `wait` gets a promise of `false` when the server refused an alt text, which then stays with its file.

### Choosing from the library

"Kiezen uit media" in the media element opens the picker: the images of the library in a dialog above the edit shell, with the folders and the number of their images on the left, the upload field of one photo below them, a search in the name and the alt text, "Nieuwste eerst" (off sorts by name), "Zonder alt-tekst" and the images as tiles, 40 at a time. "Deze gebruiken" makes the chosen photo the value of the field, saved to the draft; a photo uploaded in the picker is the choice at once, and an alt text typed for it is stored when it is used or the picker closes. Documents and videos are not shown. The folder the editor opens is remembered for the session: the next picker opens there and the side panel uploads into it, also in the next edit shell.

The picker reads the library over JSON, through endpoints the media library uses too:

| Request | Does | Answers |
|---|---|---|
| `GET /made-cms/media/files` | One page of the library through `ListMedia`: `folder`, `q` (name, alt text or the title of a page the file is on), `kind` (`image`, `document`, `video`), `without_alt` (`1`), `sort` (`newest`, the default, or `name`) and `page`. | 200 with `{"files": […], "page", "total", "more", "summary"}`, every file as `file` of the upload answer and `summary` as "12 afbeeldingen"; 422 keyed by `folder` (gone) or the filter. |
| `GET /made-cms/media/folders` | The folders from a to z through `ListMediaFolders`, counting one kind with `kind`, the images without an alt text with `without_alt` and the files a search finds with `q`. | 200 with `{"all", "folders": [{"id", "name", "count"}], "counts": {"all", "image", "document", "video", "without_alt"}}`, `counts` for the whole library. |
| `PUT /made-cms/media/chosen-folder` | Remembers the folder an editor opened for the session (`{"folder": id}`, `null` for "Alle bestanden"). | 204; 422 keyed by `folder`. |

They open for an editor who opens the pages or the media library, like uploading; `made-cms/media` itself is the address of the screen Media (see [The media library](#the-media-library)). A screen shows the list with the partial `made-cms-package-launcher::cms.partials.media-browser` and `['browser' => MediaBrowser::make($folder, $kind, $upload), 'empty' => '…']` and loads `$browser['script']`; it hears `made-cms:media-chosen` `{file}` and `made-cms:media-folder` `{folder}` from the list. The session is written back as a whole after every request, so a request that overlaps the one that remembers a folder can bring back the former folder; the open shell keeps the folder in its script.

### The media library

The screen Media (`/made-cms/media`, for an editor who opens `Screen::Media`) holds every photo, document and video of the site. Across the top are the search (name, alt text or the title of a page the file is on), one group of filters with the numbers of the whole library (every kind, a kind, or the images without an alt text) and the folders as chips. Below them are the files, as tiles or as the same tiles in rows of a list, newest first or by name, with a trail of the open folder, and a side column for the chosen file: its alt text with "Opslaan", the pages it is used on with a link to their edit shell, and "Bijsnijden" for a photo. "Uploaden" and files dropped on the files go into the open folder, through the upload field and the crop step.

The library also manages its folders, one level deep: "Nieuwe map" next to "Uploaden", and "Hernoemen" and "Verwijderen" next to the trail of the open folder (only an empty folder is deleted; otherwise the message says how many files it still holds). "Map" with "Verplaatsen" in the side column moves the chosen file into another folder or into "Alle bestanden", without a mouse. "Selecteren" next to the views, or Cmd/Ctrl- and Shift-click on a tile, selects several files to move at once, and a tile dragged onto a folder chip, or onto "Alle bestanden" in the trail, moves it there, with the whole selection when it is selected. One message below the chips says what a folder or a move did. Browsers that drag no buttons (Firefox) move files with the side column.

The address holds what the screen lists, so a reload or a link opens it the same, also on one file:

```php
// "Bekijk in Media" for a file, or the images without an alt text in a folder.
$url = route('made-cms-package.screens.media', ['file' => $media->id]);
$url = route('made-cms-package.screens.media', ['folder' => $folder->id, 'without_alt' => 1]);
```

The query takes `folder`, `kind` (`image`, `document`, `video`) or `without_alt=1`, `q`, `sort=name` and `file`; what it does not know is left out, and a folder that is gone opens "Alle bestanden". The first page of files and the folders come with the page, so the screen shows them without a request. The side column reads a file with its places, and the library manages its folders and moves the files an editor chose, through endpoints that open for the media library only:

| Request | Does | Answers |
|---|---|---|
| `GET /made-cms/media/{media}` | A file with the pages it is used on and its history, through `DescribeMedia`. | 200 with `{"file", "history", "removal", "heading", "pages": [{"title", "url", "deleted", "note", "places": [{"where", "note"}]}]}`: `file.link` the lasting link of the file, `history` the line "Geüpload door … op … · versie 2 · vervangen door …" with the version that waits (`previous`, `can_restore`), `removal` whether it can be deleted with the reason (`allowed`, `note`), `heading` as "Gebruikt op 2 pagina's" (a deleted page does not count), `url` the edit shell of the page (`null` for a deleted page), `note` for a page in the bin or places in one version only; 403; 404. |
| `POST /made-cms/media/{media}/replace` | Takes one `file` as the new version of a file, through `ReplaceMedia`. | 200 with `{"file"}`; 422 keyed by `file` for a file the library refuses or of another kind; 404. |
| `POST /made-cms/media/{media}/restore` | Puts the version that waits back, through `RestoreMediaVersion`. | 200 with `{"file"}`; 422 keyed by `version` when none waits; 404. |
| `GET /made-cms/media/{media}/download` | Saves a file, read through the app: of a photo its original as it was uploaded, of any other file the file itself (`OpenMediaDownload`). | 200 with `Content-Disposition: attachment; filename={name}.{extension}`, never cached; 404 when the file is not on the disk. |
| `POST /made-cms/media/delete` | Deletes the chosen files no page uses (`{"files": [id, …]}`, at most 200) and keeps the rest, through `DeleteMedia`. | 200 with `{"deleted", "kept", "missing", "notice", "refusal"}`: the ids, and what to tell the editor in words ("2 bestanden verwijderd.", "logo.svg staat nog op een pagina en blijft staan."); 422 keyed by `files`. |
| `PUT /made-cms/media/move` | Puts the chosen files into a folder (`{"files": [id, …], "folder": id}`, `null` for none, at most 200), all or none of them, through `MoveMedia`. | 200 with `{"files": […]}` by id, without a file that is gone; 422 keyed by `files` or `folder`. |
| `POST /made-cms/media/folders` | Adds a folder (`{"name": "…"}`, trimmed with single spaces, at most 60 characters, unique whatever its capitals) through `CreateMediaFolder`. | 201 with `{"folder": {"id", "name"}}`; 422 keyed by `name`. |
| `PUT /made-cms/media/folders/{folder}` | Gives a folder another name (`{"name": "…"}`) through `RenameMediaFolder`; its files stay in it. | 200 with `{"folder": …}`; 422 keyed by `name`; 404. |
| `DELETE /made-cms/media/folders/{folder}` | Deletes an empty folder through `DeleteMediaFolder`. | 204; 422 keyed by `folder` for a folder that holds files ("Teams heeft nog 14 bestanden. Zet ze eerst in een andere map."); 404. |

### Versions, downloading and deleting

A file of the library gets a new version under the same id with `ReplaceMedia`, so it changes on every page that shows it at once, the published ones too, without a publication. Only a file of the same kind replaces it (an image by an image, an svg or an animated gif too; a document by a document; a video by a video), because an image field on a page would break on anything else. The new version is checked and stored as an upload is, in a folder of its own (`media/{ulid}/{8 random}/`), takes the name of the upload and has no crop or focal point; the alt text, the folder and who uploaded the file stay. The version the site showed waits to be put back and is private from then on, so a visitor no longer opens it. One version waits at a time: `RestoreMediaVersion` swaps it with the version the site shows, so putting it back again undoes it, and the next replacement takes the oldest off the disk. `cms_media` keeps the number of the version, who placed it (`replaced_by`, `replaced_at`) and the version that waits (`previous`).

`OpenMediaDownload` gives a file to save, read through the app from the disk: of a photo its original as it was uploaded (full size, its own type, not cropped), of any other file the file itself.

Every file has a lasting link on the domain of the site, `/made-cms/file/{ulid}/{name}`: it sends the visitor to the address the file has now, so a link pasted into a newsletter keeps working when the file is replaced, cropped or renamed. It is open to anyone, without a session or a cookie, and gives a 404 once the file left the library. The key is the folder of the file on the disk, which its address shows already. It ends without an extension, so a rule of the web server for `\.pdf$` never takes it for a file on the disk.

`DeleteMedia` deletes only a file no page uses: a place in the draft or the published version of a page, also in a hidden strip or on a page in the bin until it is pruned, keeps it. It leaves for good, with every version, and its folder leaves the disk once the change is committed. `SyncMediaUsage` reads the files it gives a place with a shared lock, so a strip saved while its file is deleted never gets a place for a file that is gone. `MediaReplaced`, `MediaVersionRestored` and `MediaDeleted` are dispatched after the commit.

### Cropping

A photo that was scaled down keeps its original, so it can be cut again from the original as often as the editor likes. The crop step is a dialog in the CMS: "Bijsnijden" in the media element and in the side column of the media library opens it for a photo, and a photo uploaded in the picker or the media library goes there as soon as its batch is in. It shows the whole original on a stage, with the frame of the crop (drag it, pull a corner, or move it with the arrow keys), the ratios Vrij, 16:9, 4:3, 3:2, 1:1 and Origineel, turning left and right, mirroring, "Herstel" and the focal point. On the right the editor switches "Verkleinen voor het web" (at most `media.image.max_width`; off keeps the size of the crop, still as `media.image.format`), with the size of the original and an estimate of the photo, and gives the file its name, its alt text and, with access to the media library, its folder. "Opslaan" stores it; "Zo laten, zonder snijden" stores only the fields on the right. A photo that is on pages says on how many: a crop changes the file, so it changes on every page at once, the published ones too, without a publication.

In code:

```php
use Made\CmsPackage\Content\Actions\CropImage;
use Made\CmsPackage\Content\Actions\MoveMedia;
use Made\CmsPackage\Content\Actions\RenameMedia;
use Made\CmsPackage\Content\Data\CropArea;
use Made\CmsPackage\Content\Data\CropMedia;

$media = CropImage::run($media, new CropMedia(
    area: new CropArea(x: 240, y: 344, width: 3520, height: 1980, rotation: 90, flip: false),
    focalX: 0.46,       // from 0 (left) to 1 (right) of the photo that comes out
    focalY: 0.44,       // from 0 (top) to 1 (bottom)
    scaleDown: true,
));

RenameMedia::run($media, 'Teamfoto Merwestad 4');   // becomes teamfoto-merwestad-4
MoveMedia::run([$media->id, $other->id], $folder->id); // null for "Alle bestanden"
```

The area is in pixels of the original as the editor sees it: upright (by its EXIF orientation), turned clockwise by `rotation` (0, 90, 180 or 270), then mirrored left to right when `flip`; `CropArea::onOriginal()` takes it back onto the original. An area outside the photo is refused with a `ValidationException` keyed by `crop`, and a file without an original (an svg, an animated gif, a document, a video) throws `MediaNotCroppable`. The crop is stored in the column `crop` of `cms_media` (null for the whole original, not turned and scaled down), so the next crop starts where the editor left off, and the upright size of the original in `original_width` and `original_height`. When only the focal point changes no file is written.

Every crop writes the photo at a new address in a folder of its own next to the original (`media/{ulid}/{random}/{name}.webp`) with a new thumbnail, so no browser or CDN keeps the former photo; the former files leave the disk after the commit. A page that a host or CDN cached keeps pointing at the former address until its cache runs out. Renaming moves the photo to the new name in its folder, so its address changes too. `CropImage`, `RenameMedia` and `MoveMedia` dispatch `MediaCropped`, `MediaRenamed` and `MediaMoved` after the commit.

The site uses the focal point as `object-position` (see [Images](#images)), so a photo cut to fit a phone keeps what matters in view.

A screen of the CMS includes the step with the partial `made-cms-package-launcher::cms.partials.crop` and `['crop' => CropStep::make($moves)]` (`$moves` when the editor opens the media library) and loads `$crop['script']`. It opens the step with `made-cms:crop` `{files, opener, upload, done(results)}` on the dialog, the files as the upload field gets them; `done` gets `[{file, handled}]` when the step closes, and every file of which anything was stored bubbles up as `made-cms:media-changed` `{file}`. The step sets `taken` on the detail when it opens for the files; when it stays unset (the step is open already, or its script did not load) there is no `done` to wait for. An upload field given `crop: true` (`UploadField::make($folder, crop: true)`) hands the photos of a batch to the step on the page itself, while the editor still sees the field; a photo that comes in after its dialog closed, or that the step does not take, asks its alt text in the field.

### Thumbnails

A photo that was scaled down gets a thumbnail when it is uploaded: the photo as the site shows it, scaled down until it just covers 480 × 360 (a tile of the library on a sharp screen; never up, never cropped), as `thumb-{random}.webp` in its folder (`media.image.format` at `media.image.quality`). It is made from the photo the site shows, not from the original. The tiles of the picker, the choice at its foot and the row of a photo in the upload field load it, so a page of 40 tiles loads a few dozen kB per photo instead of the full photo; the media element of the side panel shows the photo itself. A strip never renders the thumbnail, so the site keeps the photo.

The CMS gets it as `thumb` of a file: the url of the thumbnail, of the file itself for an image without one (an svg, an animated gif) and `null` for a document or a video. An svg, an animated gif, a pdf and an mp4 get no thumbnail.

Photos uploaded before the library made thumbnails have none yet; their tiles load the photo itself until they get one. Give them one with:

```bash
php artisan made-cms:media-thumbnails
```

It reads each photo from the media disk, skips a photo that has a thumbnail, and names a photo whose file is not on the disk or cannot be read, while the others go on; the command then fails. `MakeMissingThumbnails::run()` does the same in code and `MakeThumbnail::run($contents)` makes the thumbnail of one photo without storing it.

### Where a file is used

The table `cms_media_usages` holds a row for every place a file is used: a field of a strip in the published version of a page, and in its draft while there is one. A hidden strip counts, an image in an item of a list counts by its own place, and a deleted page keeps its rows while it can be restored. Every change of the strips through the Content Actions (saving, adding, moving, hiding, removing and restoring a strip, publishing, discarding the draft) brings the rows of the page up to date in the same transaction, so the host never writes them.

```php
use Made\CmsPackage\Content\Actions\ListMediaPlaces;
use Made\CmsPackage\Content\Models\Media;

Media::query()->inUse()->count();               // files on a page, in a draft or on a deleted page
Media::query()->withoutAlt()->inUse()->count(); // images on a page without an alt text

$places = ListMediaPlaces::run($media);         // load a list with Media::query()->withUsage()

$places->pageCount();                           // "Gebruikt op 2 pagina's"
$places->places[0]->pageTitle;                  // "Onze teams"
$places->places[0]->where;                      // "Hero › Achtergrondfoto", or "Fotorij › Foto 3 › Afbeelding" in a list
```

`where` comes from the labels of the strip type and its fields (an item of a list by its `itemLabel()` and number); when the type left the catalogue or no longer has the field it is the keys, `hero › foto`. A place has the page, the strip, the path of the field (`fotos.2.afbeelding`), the versions it is in (`$place->in(UsageVersion::Published)`) and whether its page is deleted. A file that is used cannot be deleted: the foreign key of the table refuses it.

Content written without the Actions (a seeder with the page factory, an import) has no rows yet. Build them from the strips of every page with:

```bash
php artisan made-cms:media-usage
```

Run it once after updating a site that already had content, and after such a write. A strip whose type left the catalogue cannot be read, so it keeps the rows it had.

Config keys: `media.disk`, `media.max_upload_kb`, `media.allowed_mimes`, `media.image.driver`, `media.image.max_width`, `media.image.format` and `media.image.quality`.

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
- With a Content Security Policy, allow `'self'` for `script-src`, `style-src`, `font-src` and `connect-src`. A Vite CSP nonce is added to the script automatically. When the media library stores its files on another domain, such as a Space, allow that domain in `img-src` (and `media-src` for video) too; see [DigitalOcean Spaces and other S3 disks](#digitalocean-spaces-and-other-s3-disks).

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
- **Codes and links are bound to the browser** that asked for them, work once, expire after `login_code.ttl_minutes` (10) and stop after `login_code.max_attempts` (5) wrong tries. A new code only replaces the code of the same browser, so nobody locks an editor out by asking for codes elsewhere. Opening the mail link only shows a confirmation page, so mail scanners never use it up. Codes are stored as an HMAC with your `APP_KEY`.
- **Equal time for every address.** A password login checks exactly one hash on every path, also for an unknown address: a stand-in hash made with your hash settings is kept in the cache.
- **Rate limits** on what was typed and where it comes from (an IPv4 address, or an IPv6 address per /64), never on whether an account exists:
  - `made-cms-package.login-code` (asking for a code): 3 per 10 minutes per address and network, 20 an hour per network, 10 an hour per address;
  - `made-cms-package.password`: 10 a minute per address and network, 30 a minute per network, 50 an hour per address;
  - `made-cms-package.login` (checking a code or the mail link): 10 a minute per address and network, 30 a minute per network;
  - `made-cms-package.two-factor`: 5 a minute per editor and network, 10 an hour per editor.

  A browser that logged in with the address before counts on its own for the limits per address, so someone who exhausts them cannot lock an editor out on their own device. An editor who runs into the limit of the password or the second factor can still log in with a code by mail. The limits and the stand-in hash need a cache store that is shared between your servers and survives a request.
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
├── Content/    pages, strips, publishing and the media library
│   ├── Strips/     the strip type definition API: StripType, the field types and the StripTypes catalogue
│   ├── Actions/    e.g. RenderStrips, the strips of a page ready to render, and UploadMedia
│   └── ...
├── Console/    technical layer: artisan commands
├── Http/       technical layer: middleware (the launcher, page lookup), the Route::madeCms() macro, the <x-made-cms::strips>, <x-made-cms::heading> and <x-made-cms::emphasis> components, controllers grouped per context, the login session
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
