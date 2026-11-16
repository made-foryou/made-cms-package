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

- confirm the app is a Laravel 12 or 13 project on PHP 8.4+ with `ext-dom` and `ext-fileinfo`; for uploaded photos GD with WebP support (or Imagick), `exif`, and a `memory_limit` of at least 256 MB (GD takes about 140 MB to crop a photo of 12 MP), and for uploads through the CMS an `upload_max_filesize` and `post_max_size` of at least `media.max_upload_kb` (25 MB by default)
- inspect the target code paths where the package should be applied

### 2. Install

- `composer require made-foryou/made-cms-package`; the service provider is auto-discovered
- publish and run the migrations: `php artisan vendor:publish --tag="made-cms-package-migrations"` and `php artisan migrate`
- the media library stores files on `media.disk` (env `MADE_CMS_MEDIA_DISK`, default `public`): run `php artisan storage:link`, or use a local disk with a `url` or a cloud disk; a local disk without `url` throws `MediaDiskNotUsable` the first time the library is used

### 3. Launcher (the MADE tile on the website)

- the tile and its login panel appear automatically on every full HTML page of the `web` middleware group; do not add middleware, views, assets or Blade directives to the host
- it is only shown to remembered editors and to IP addresses in `MADE_CMS_LAUNCHER_IPS` (comma separated, CIDR allowed); set it in `.env`, not in code
- switch it off with `MADE_CMS_LAUNCHER_ENABLED=false` (for example in `phpunit.xml`) or `config(['made-cms-package.launcher.enabled' => false])`
- exclude extra paths through `launcher.except` in the published config (`php artisan vendor:publish --tag="made-cms-package-config"`)
- behind a proxy, configure `trustProxies` in `bootstrap/app.php`, otherwise the IP allowlist sees the proxy address
- the launcher markup, styles and script are not publishable: they ship and update together with the package

### 4. Editors and logging in

- editors are CMS accounts of their own (`cms_editors`), separate from the app's users; never point them at `App\Models\User`
- create one with `php artisan made-cms:editor <email> --name="..." --role=admin` (empty password = code login only; leave `--role` out to pick roles from a list), or `CreateEditor::run(new NewEditor(email: ..., name: ..., password: null, roles: [Role::Admin]))` in a seeder
- editors log in through the panel the tile opens, or on the login page at `/made-cms` (the `routes.prefix`; link to it from invitation mails with `route('made-cms-package.login')`), both through the package's own `made-cms` session guard; it never logs anyone in to the app; check a CMS login with `auth('made-cms')->check()`
- set `APP_URL` and `MAIL_FROM_ADDRESS`: the login mail is sent right after the response through the app's mailer, never queued, and its link is built from `APP_URL`
- use a shared, persistent cache store in production: the login rate limiters and the stand-in password hash live there
- name a page in the edit bar with `<meta name="made-cms:page" content="...">`; a fixed header can offset itself with the `--made-cms-bar-height` css variable

### 5. Roles and screens

- every editor holds at least one fixed role (`Made\CmsPackage\Access\Enums\Role`): `admin` (every screen), `webmaster`, `content-manager`, `seo-specialist`, `owner`; the roles decide which CMS screens (`Screen` enum) the editor opens
- change roles with `AssignRoles::run(new RoleAssignment(by: $admin, editor: $editor, roles: [...]))`; it throws `RolesRejected` when the editor making the change may not (only admins and webmasters change roles, only an admin gives or takes `admin`, the last admin keeps it)
- read access with `ListScreens::run($editor)` or `CheckScreenAccess::run(new ScreenVisit($editor, Screen::Redirects))`, and find editors with `Editor::query()->withRole(Role::Admin)`
- the package registers a route per screen at `/made-cms/<screen>` (`route('made-cms-package.screens.redirects')`), guarded by `EnsureScreenAccess`; the pages screen and the media library are built, the other screens show that they are coming until they are
- guard a route of your own for a CMS screen with `->middleware(EnsureScreenAccess::using(Screen::Redirects))`
- the CMS start page is `/made-cms/start` (`route('made-cms-package.start')`): every logged-in editor opens it, with a menu of the screens of their roles; the edit bar and the login page link to it, so the host adds nothing

### 6. Strip types

- editors build pages from the site's strip types: one class per type in `app/Cms/Strips` that extends `Made\CmsPackage\Content\Strips\StripType` (config `strips.path` and `strips.namespace`); the package finds them on its own
- implement `key()` (fixed, lowercase letters, digits and dashes, e.g. `hero`) and `fields()`; optionally `label()`, `description()` (both shown in the catalogue editors add strips from), `view()` (the Blade view, default `strips.{key}`) and `with(array $values)` (extra data such as the latest news; it receives the rendered values and its keys land next to the fields)
- field types in `Made\CmsPackage\Content\Strips\Fields`: `Text`, `LongText`, `RichText` (cleaned HTML: p, strong, em, ul, ol, li, a), `Link` (`{"page": id}` or `{"url": "…"}`), `Button` (text + link), `Heading` (a text with its level, `{"text": "…", "tag": "h2"}`: `h1`–`h6` or `span` for no heading; `->tag('h1')` sets the level of the design, `h2` by default, `->tags(['h1', 'h2'])` limits the choice; editors who open Meta-overzicht (SEO specialist, admin) choose the level in the side panel, the others only see it; a text stored while the field was a `Text` counts as the default level, so switching needs no migration), `Image` (an image of the media library, stored as the id of its file; no `default()`), `Choice` (list, `value => label` or a backed enum) and `Items` (a list of items with the same fields, `min()`/`max()`, `itemLabel('Knop')` for "Knop toevoegen" in the side panel; every item gets a fixed `id`); every field takes `label()`, `hint()`, `required()` and `default()`; labels and hints are what editors see in the side panel, so write them in the editors' language
- strip values are validated with the package's own Dutch and English messages (`StripType::messages()`, `strips.validation.*` in the published translations), not the app's `validation.*` lines
- the field names `id`, `type` and `visible` are reserved, and `id` inside an item; a broken definition (also a heading level that is no tag, a default level outside `tags()`, or an image with a default) throws `InvalidStripType` or `DuplicateStripType` as soon as the catalogue (`app(StripTypes::class)`) is built

### 7. Use the actions

- a page holds its published strips (`strips`) and a draft (`draft_strips`, `null` without one, and again once a change is taken back); every strip has an `id`, a `type` key, `visible` and `values`
- `AddStrip::run($page, 'hero', $position)`, `MoveStrip::run($page, $stripId, $position)`, `RemoveStrip::run($page, $stripId)` (returns the removed `Strip`), `RestoreStrip::run($page, $strip, $position)` (puts a removed strip back with its own id, checked against its type), `UpdateStrip::run($page, $stripId, $values)` (merges with the stored values: pass only the fields to change, `null` clears one; throws a `ValidationException` keyed by field) and `SetStripVisibility::run($page, $stripId, $visible)` change the draft; visitors keep the published strips; every change reads the page again with its row locked, so changes at the same time never overwrite each other, and `$page` holds the stored state afterwards
- `PublishPage::run($page)` makes the draft the published version and refuses a draft with an invalid strip (errors keyed `strips.<id>.<field>`); `DiscardDraft::run($page)` goes back to the published version; query pages with `Page::query()->published()`
- `CompareDraft::run($page)` (`DraftChanges`) says what the draft changes: the strip ids it adds, changes (fields or visibility), removes or moves, `count()`, and whether the page can be published or discarded; a missing field counts as its default, key order and item ids do not count
- a position is the place among all strips of the draft, also hidden ones and strips of a type that left the catalogue; `ListEditableStrips::run($page)` lists them all in that order with `known`, `visible` and `summary` (`StripType::summary($values)`: the first field with text, else the titles of the items of a list)
- a strip whose type is no longer in the catalogue keeps its data; only `UpdateStrip` refuses it
- to change one item of a list, pass the whole list: items with the `id` of a stored item keep the fields you leave out, items without a known `id` are new, items you leave out are removed
- editors manage pages at `/made-cms/pages` (`route('made-cms-package.screens.pages')`): the tree in menu order, a new page at `pages.create`, one page at `pages.show`, deleting and restoring; the host adds nothing
- pages form a tree: `parent_id`, `position` among the siblings and `in_menu`; the address is the parent's address plus the slug (`/` + slug at the top and under the home page) and every write keeps the addresses of the pages under it in step
- create and change pages with `CreatePage::run(new NewPage(title: 'Team', slug: null, parentId: $about->id))`, `UpdatePage::run($page, new PageChanges(...))`, `MovePage::run($page, $parentId, $position)`, `DeletePage::run($page)` and `RestorePage::run($page)`; read the tree with `ListPages::run()` (`PageTree`, `->flatten()` in menu order; `->inMenu()` only follows the switch and keeps drafts, so it is the tree of the CMS); build the menu of the site with `ListMenuPages::run()` (the published pages with the menu switch on, in menu order; a draft or a page outside the menu is left out with the pages under it; `->roots` for one level, `$item->title` and `$item->path?->value` per item); never write `parent_id`, `position` or `cms_routes.path` yourself
- refusals are exceptions: `PagePathTaken`, `InvalidPageSlug`, `CircularPageMove`, `HomePageProtected` (the home page keeps `/`, stays at the top and is never deleted), `PageHasChildren` (move or delete the pages below first)
- deleting is a soft delete that frees the address; `made-cms:prune` removes pages deleted more than `pages.keep_deleted_days` (30) days ago and the package schedules it daily, so run the scheduler (`schedule:run`) in production
- add files to the media library with `UploadMedia::run(new UploadedMedia(path: $localFile, filename: 'Teamfoto.jpg', folderId: null, uploadedBy: null))`: the type is read from the contents, a photo becomes a WebP of at most 1920 px with the upload and a thumbnail for the tiles of the CMS (covering 480 × 360) kept next to it, an svg that does more than draw is refused, and a refusal is a `ValidationException` keyed by `file`; set the alt text with `UpdateAltText::run($media, $alt)` (one per file, for every place); read with `Media::query()->images()`, `->ofKind($kind)`, `->inFolder($folder)`, `->withoutAlt()`, `->search($term)` (name, alt text or the title of a page the file is on), `->newestFirst()`, `->byName()` and `->countPerKind()`, a page of 40 files with their urls with `ListMedia::run(new MediaFilter(folderId: …, search: …, kind: MediaKind::Image))` and the folders with their counts with `ListMediaFolders::run($kind)`, a file with the pages it is on with `DescribeMedia::run($media)`; never write to the media disk yourself; once on a site that had photos before the thumbnails, run `php artisan made-cms:media-thumbnails`
- crop a photo again from its original with `CropImage::run($media, new CropMedia(new CropArea(x: 0, y: 125, width: 2400, height: 1350, rotation: 0, flip: false), focalX: 0.5, focalY: 0.4, scaleDown: true))` (the area in pixels of the original upright, turned clockwise by `rotation`, then mirrored; an area outside the photo is a `ValidationException` keyed by `crop`, an svg, animated gif, document or video throws `MediaNotCroppable`), rename a file with `RenameMedia::run($media, 'Teamfoto Merwestad')` (a slug, its address changes) and move it with `MoveMedia::run([$media->id], $folderId)` (one or more files, all or none); each changes the file on every page at once, the published ones too, and gives a cropped or renamed photo a new address, so never store or cache the url of a file
- manage the folders of the library (one level deep) with `CreateMediaFolder::run('Teams')`, `RenameMediaFolder::run($folder, 'Teams 2026')` and `DeleteMediaFolder::run($folder)`: a name is trimmed, at most 60 characters and unique whatever its capitals (refused with a `ValidationException` keyed by `name`), and only an empty folder is deleted (refused keyed by `folder` with the number of its files)
- the package keeps where every file is used in `cms_media_usages` on every change through its Actions; read it with `Media::query()->inUse()` and `ListMediaPlaces::run($media)` (`pageCount()`, and per place `pageTitle` and `where`, e.g. "Hero › Achtergrondfoto"); after writing strips without the Actions (a seeder with the page factory, an import) and once on a site that had pages before, run `php artisan made-cms:media-usage`
- the media library is the screen `/made-cms/media` (for editors with the media screen: admin, webmaster, content manager): search by name, alt text or page title, filters with numbers, folders, tiles or a list, a side column with the alt text, the pages a file is on and "Bijsnijden", and uploads with the crop step; link to it with `route('made-cms-package.screens.media', ['file' => $media->id])` or with `folder`, `kind`, `without_alt=1`, `q`, `sort=name`; the app needs no code for it
- put an image in a strip as the id of its file (`UpdateStrip::run($page, $stripId, ['foto' => $media->id], chooseLevels: false)`, or `'foto' => $media->id` in a seeder); saving or publishing refuses a file that is gone or is no image, keyed by the field
- listen to `EditorLoggedIn`, `EditorLoggedOut`, `EditorRolesChanged`, `TwoFactorEnabled`, `PageCreated`, `PageUpdated`, `PageMoved` (old and new address), `PageDeleted`, `PageRestored`, `StripAdded`, `StripMoved`, `StripRemoved`, `StripUpdated`, `StripVisibilityChanged`, `DraftDiscarded`, `PagePublished`, `MediaUploaded`, `AltTextUpdated`, `MediaCropped`, `MediaRenamed` and `MediaMoved`, `MediaFolderCreated`, `MediaFolderRenamed`, `MediaFolderDeleted` for side effects (clear a cache of your own when a file changed); the content events (`Page*`, `Strip*`, `DraftDiscarded`, `PagePublished`, `Media*`, `AltTextUpdated`) arrive after the commit of the transaction they are dispatched in

### 8. Serve the pages

- serve the CMS pages with one line as the **last** line of `routes/web.php`: `Route::madeCms(fn (Page $page) => view('page', ['page' => $page]))` (Blade), `Route::madeCms(fn (Page $page) => Inertia::render('Page', ['title' => $page->title, 'strips' => RenderStrips::run($page)->toArray()]))` (Inertia), or `Route::madeCms(ShowPage::class)` with an invokable class
- the macro catches every `GET` address no route above it claims, looks up the published page there and hands it to the responder; no page or a page that is not published answers 404; routes of the site above it win
- it takes the prefix and middleware of a surrounding group, returns the route without a name (chain `->name()` or `->middleware()`), can be used more than once and works with `route:cache`
- every page has one address: a `GET` or `HEAD` for another form (`/Over-Ons`, `/over-ons/`, `//over-ons`) gets a `301` to the lowercase address without a trailing slash, under the group prefix and with the query string; link to `$page->route->path`, never to a form of your own
- the edit mode: the CMS loads a page in an iframe on the same origin with `?made-cms=edit`; for a logged-in editor the responder gets the page whatever its status and `RenderStrips`/`<x-made-cms::strips>` render the draft, sent with `no-store`, `X-Frame-Options: SAMEORIGIN` and `noindex` and without the launcher; the responder needs no code for it, but must cope with a draft page (`published_at` can be `null`)
- the preview: `?made-cms=preview` serves the same for an editor outside the frame ("Voorvertoning" opens it in a new tab); visitors and guests get what they always get
- the address of a page is `$page->route->path` (`/` for home, `/over-ons/team` nested), stored in `cms_routes`; look up what a visitor sees with `FindPublishedPage::run(PagePath::fromString($path))`

### 9. Render the strips

- `RenderStrips::run($page)` gives the strips a visitor sees: published (the draft in the edit mode), in order, without hidden strips or strips of a type that left the catalogue; `->toArray()` is `[{id, type, ...fields}]`
- rendered values: a `Link` is a url (a page as its current path, `null` when it is gone or not published, or when the address is one the validation would refuse), a `Button` is `{text, url}` or `null`, a `Heading` is `{text, tag}` or `null` without a text, `Items` is always a list with the `id` and every field in every item (use the `id` as the key of a list), a missing field gets its default, `RichText` is cleaned HTML (also a value a seeder or an import stored, or a default) or `null`, an `Image` is `{url, alt, width, height, focal: {x, y}}` (the alt text of the file, `''` without one; no size for an svg; the focal point editors set in the crop step, from 0 to 1, the centre without one: use it as `object-position` with `object-fit: cover`) or `null` without an image or when its file is gone or no image, so show a fallback of your own; `RenderStrips` finds the files of a page in one query
- Blade: `<x-made-cms::strips :page="$page" />` renders every strip with `resources/views/strips/<key>.blade.php` (or the `view()` of its type) and passes `$strip`: `{{ $strip->kop }}`, `{!! $strip->intro !!}`; a missing view throws `MissingStripView`; print a heading with `<x-made-cms::heading :heading="$strip->kop" field="kop" class="hero__kop" />` (the element of its level, nothing without a text; in a list `field` names the field of the item)
- React or Vue: pass `RenderStrips::run($page)->toArray()` as a prop and map `strip.type` to a component of your own; print rich text with `dangerouslySetInnerHTML` or `v-html`, a heading with its tag (`const Kop = strip.kop?.tag` in React, `<component :is="strip.kop.tag">` in Vue)
- put `data-cms-strip` with the strip id on the root element of every strip (required); `data-cms-field="<field>"` and `data-cms-items="<field>"` are optional and static
- the edit shell (`/made-cms/pages/{page}/edit`, "Bewerken" in the edit bar) outlines every `data-cms-strip` in the frame, also strips rendered in the browser after load; a visible strip without it is reported in the browser console, and links and forms of the site do nothing in the frame
- without a chosen strip the side panel shows the opbouw: editors add strips from the catalogue (every strip type, by `label()` with its `description()`), move them by dragging or with the arrow keys and remove them with "Ongedaan maken"; a strip of a type the site no longer has stays listed as "Niet op de site" and can only be removed. Its JSON routes (`made-cms-package.pages.strips.index`, `.store`, `.position`, `.destroy`, `.restore`) need no code in the app; the frame reloads after every change, so a strip view only has to render what it gets
- choosing a strip opens its fields in the side panel; editors' changes are saved as the draft (always every field, through `UpdateStrip`), so a strip view needs nothing extra to show them; the panel's JSON routes (`made-cms-package.pages.strips.*`) need no code in the app
- an `Image` field is a media element in the panel: editors choose a photo from the media library ("Kiezen uit media" opens a picker with the images, their folders and a search), upload one into it (it goes into the media library and is the value of the field at once, saved as the draft) or take it out with "×"; the folder an editor opens in the picker is where the next upload goes for the rest of the session. "Bijsnijden" (and a photo uploaded in the picker) opens the crop step: frame, ratios, turning, mirroring, the focal point, scaling down, the file name and the alt text; a crop changes the file on every page at once, and the frame reloads. The app needs no code for it beyond the PHP upload limits and the media disk above; the picker's and the crop step's JSON routes (`made-cms-package.media.files`, `.folders`, `.chosen-folder.update`, `.crop.show`, `.crop.store`, `.original`, `.name.update`, `.move`) are the package's. Use `hint()` to say what works best ("Minimaal 1600 px breed.", "Vierkant werkt het best."), it stands next to the label
- watching along: an element with `data-cms-field` follows what the editor types without a reload, and clicking it on the site opens the field in the panel; anything else reloads the frame after the save. Put `data-cms-field` on the element that holds only the text (a text, long text or button), on an element with `href` (a link, a button), on the element that prints rich text unescaped, or on the `<img>` of an image (without `srcset`, not in a `<picture>`; the shell sets `src` and `alt`, and `width`/`height` to the size of the file when the view prints them, so size an icon with css instead). An `<img>` the view only prints when there is an image shows once the frame reloads after the save. In `data-cms-items` render one direct child per item, in order; a field inside it uses the key of the item's field (`titel`)
- "Ongedaan maken" in the panel takes the last saved change of a strip back, one step per click, for as long as the shell is open
- the SEO specialist and the admin see a tab "Koppen" next to the opbouw: every heading of the page as the frame shows it, with signals for no H1, more than one H1, a skipped level and an empty heading; a heading of a `Heading` field opens it, a heading the layout or a strip view prints is fixed. Print headings as real h1–h6 elements (with `<x-made-cms::heading>` for a field) and do not leave an empty heading element in the layout
- the bar of the shell shows "Concept · n wijzigingen" (`CompareDraft`), publishes with "Publiceren" (`PublishPage`; a strip that breaks its definition is named and chosen instead), throws the draft away with "Concept weggooien" (`DiscardDraft`) and opens "Voorvertoning"; every editor who opens `Screen::Pages` may publish; its JSON routes (`made-cms-package.pages.draft.show`, `.draft.destroy`, `made-cms-package.pages.publish`) need no code in the app
- a page link is the path in `cms_routes` without the prefix of a route group; a site under `/nl` adds that itself

## Rules, References, and Templates

Read before executing:

- no additional resource files for this skill

## Examples

- showing the tile to the office network: add `MADE_CMS_LAUNCHER_IPS=203.0.113.0/24` to the production `.env`
- a host feature test that snapshots full HTML: disable the launcher in `phpunit.xml` with `<env name="MADE_CMS_LAUNCHER_ENABLED" value="false"/>`
- pages in a second language: `Route::prefix('en')->group(fn () => Route::madeCms(fn (Page $page) => view('page', ['page' => $page])));`
- a first editor in production: `php artisan made-cms:editor editor@example.com --name="Menno Tempelaar" --role=admin`

## Anti-patterns

- do not treat the tile's visibility as authorisation; it only hides the door
- do not add a login form, guard or middleware for editors to the app; the package brings its own
- do not store roles on the app's users or write `cms_editors.roles` directly; roles change through `AssignRoles` so its rules hold
- do not build a menu or dashboard of your own that hides links as authorisation; the route middleware decides, `ListScreens` only feeds the menu
- do not queue or log `LoginCodeIssued`: its payload holds the plain code
- do not create pages or addresses with `Page::create()` or `$page->route()->create()` in app code; `CreatePage` keeps the tree and the addresses consistent
- do not put a CDN or response cache in front of pages for allowed visitors without excluding the tile
- do not derive `key()` from the class name or change it once content uses it; the key is stored with every strip
- do not nest `Items` inside `Items`; one list level is supported
- do not put routes of the site below `Route::madeCms()`; they are never reached
- do not call the responder's parameter `$path`; the route parameter has that name and implicit binding would look the page up by id
- do not chain `Route::middleware(...)->madeCms()`; wrap the macro in `->group()` instead
- do not look pages up by `cms_pages.slug`; an address lives in `cms_routes`
- do not hand `$page->strips` to a view or a prop; it is the stored form with page ids and hidden strips, render through `RenderStrips`
- do not print a `Text` or `LongText` field unescaped; only `RichText` is cleaned HTML
- do not write `cms_media_usages` yourself; the Actions keep it in step with the strips, and its foreign key keeps a file in use from being deleted
- do not call `StripType::render()` yourself for an image or store a url or path in a strip; render through `RenderStrips`, which hands the files of the library over, and store the id of the file
- do not link to the original of a photo or keep the url of a file outside the package; a crop or a new name moves the photo to a new address and takes the former file off the disk
- do not hard-code the element of a `Heading` or style headings by tag (`h2 { … }`); print the tag from the value and give it its look with a class, because the level is a value of the field, not of the view
- do not send `X-Frame-Options: DENY` or `Content-Security-Policy: frame-ancestors 'none'` from the site's middleware or web server; the edit mode needs a frame of the same origin (`SAMEORIGIN`, `frame-ancestors 'self'`)
- do not let a full-page cache store responses with `?made-cms=edit` or `?made-cms=preview`
- do not leave out `data-cms-strip` on the root element of a strip view; the edit shell finds the strip by it
- do not document package internals here; keep the skill focused on adoption in Laravel apps
