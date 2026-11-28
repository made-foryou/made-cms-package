<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\CompiledRouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\AnimationFactoryInterface;
use League\Flysystem\Filesystem as Flysystem;
use Made\CmsPackage\Access\Actions\RememberDevice;
use Made\CmsPackage\Access\Enums\LoginMethod;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\CombinePageScans;
use Made\CmsPackage\Content\Actions\RecognisePage;
use Made\CmsPackage\Content\Data\GeneratedStripTypes;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\RecognisedField;
use Made\CmsPackage\Content\Data\RecognisedPage;
use Made\CmsPackage\Content\Data\ScanField;
use Made\CmsPackage\Content\Data\ScannedPage;
use Made\CmsPackage\Content\Data\ScanResult;
use Made\CmsPackage\Content\Data\ScanType;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Enums\ScanDoubt;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Route as PageRoute;
use Made\CmsPackage\Content\PageTypes\PageType;
use Made\CmsPackage\Content\PageTypes\PageTypes;
use Made\CmsPackage\Content\Scan\ScannedStripType;
use Made\CmsPackage\Content\Scan\StripTypeSource;
use Made\CmsPackage\Content\Strips\StripType;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Http\Cookies\EditorDevice;
use Made\CmsPackage\Tests\Fixtures\Filesystem\RecordingAdapter;
use Made\CmsPackage\Tests\Fixtures\Hashing\CountingHasher;
use Made\CmsPackage\Tests\Fixtures\PageTypes\Valid\Nested\LandingPage;
use Made\CmsPackage\Tests\Fixtures\PageTypes\Valid\TeamPage;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Hero;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Nested\Cards;
use Made\CmsPackage\Tests\TestCase;
use Pest\Expectation;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;

/*
|--------------------------------------------------------------------------
| Suites
|--------------------------------------------------------------------------
|
| Feature and Unit tests boot the package through Testbench. Feature tests
| also get a fresh in-memory database. Arch tests stay on the plain PHPUnit
| base: they only inspect source files and never boot the application.
| Browser tests drive Chromium through Playwright against the app in this
| process; they form the suite "Browser" that only composer test:browser runs.
| Other runs exclude the suite, not a group: loading a browser test already
| starts Playwright.
|
*/

pest()->extend(TestCase::class)->in('Unit');

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Browser');

// The longest a browser action or assertion waits; a passing test takes no longer for it. The Windows runner in CI
// serves the first pages of a run slowly enough (cold PHP server, views compiled on first use) to pass the default 5 s.
pest()->browser()->timeout(15_000);

/*
|--------------------------------------------------------------------------
| Databases
|--------------------------------------------------------------------------
|
| The tests run on SQLite, MySQL and PostgreSQL (tests/Fixtures/Database).
| They never count on what only one of them does: the order of the keys of
| stored JSON, the quotes in the text of a query, or an id that no row has.
|
*/

/**
 * A value read back from a JSON column, compared as toBe() does but without the order of the keys of an object:
 * MySQL stores those sorted (by length, then alphabetically), SQLite and PostgreSQL as they were written. A list
 * keeps its order.
 */
expect()->extend('toBeStoredAs', function (mixed $expected): Expectation {
    expect(withSortedKeys($this->value))->toBe(withSortedKeys($expected));

    return $this;
});

function withSortedKeys(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    $value = array_map(withSortedKeys(...), $value);

    if (! array_is_list($value)) {
        ksort($value, SORT_STRING);
    }

    return $value;
}

/**
 * A table or column as the grammar of the connection quotes it in a query: "cms_media" on SQLite and PostgreSQL,
 * `cms_media` on MySQL.
 */
function quoted(string $identifier): string
{
    return DB::getQueryGrammar()->wrap($identifier);
}

/**
 * A page of the tree as ListPages builds it, for tests of the tree and what reads it.
 *
 * @param  list<PageNode>  $children
 */
function pageNode(
    int $id,
    string $path,
    array $children = [],
    bool $inMenu = true,
    ?int $parentId = null,
    int $depth = 0,
    PageStatus $status = PageStatus::Draft,
    bool $hidden = false,
    ?string $title = null,
    bool $hasDraft = false,
    bool $isEmpty = true,
    ?int $updatedBy = null,
    ?CarbonImmutable $draftStartedAt = null,
): PageNode {
    return new PageNode(
        id: $id,
        title: $title ?? 'Pagina '.$id,
        slug: 'pagina-'.$id,
        path: PagePath::fromString($path),
        status: $status,
        inMenu: $inMenu,
        hidden: $hidden,
        position: 0,
        parentId: $parentId,
        depth: $depth,
        hasDraft: $hasDraft,
        isEmpty: $isEmpty,
        updatedAt: CarbonImmutable::parse('2026-11-03 10:00:00'),
        updatedBy: $updatedBy,
        draftStartedAt: $draftStartedAt,
        children: $children,
    );
}

/**
 * An id no row has. MySQL and PostgreSQL keep counting ids after a test rolls back, so a small number such as 999
 * can belong to a row a later test creates; the tests never create this many.
 */
function missingId(): int
{
    return 2_000_000_000;
}

/**
 * Lets another request take an address at the same moment: right before this request writes the address of a page
 * (a route it creates or updates), a row appears at that address, after any check that it was free. It happens once.
 */
function claimAddressFirst(): void
{
    $claimed = false;

    $claim = static function (PageRoute $route) use (&$claimed): void {
        if ($claimed) {
            return;
        }

        $claimed = true;
        DB::table('cms_routes')->insert([
            'path' => $route->path,
            'routable_type' => 'cms_page',
            'routable_id' => missingId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    };

    PageRoute::creating($claim);
    PageRoute::updating($claim);
}

/*
|--------------------------------------------------------------------------
| Architecture helpers
|--------------------------------------------------------------------------
*/

/**
 * The bounded contexts under src/. Add a new context here so the arch tests cover it.
 *
 * @return list<string>
 */
function contexts(): array
{
    return ['Access', 'Content'];
}

/**
 * The context namespaces for one layer (e.g. "Actions") that exist on disk.
 *
 * Arch expectations on a namespace without classes are meaningless, so layers
 * only get checked once their first class exists.
 *
 * @return list<string>
 */
function contextNamespaces(string $layer): array
{
    $namespaces = [];

    foreach (contexts() as $context) {
        if (is_dir(__DIR__."/../src/{$context}/{$layer}")) {
            $namespaces[] = "Made\\CmsPackage\\{$context}\\{$layer}";
        }
    }

    return $namespaces;
}

/**
 * The fully qualified class names of every class file in a context layer.
 *
 * @return list<class-string>
 */
function contextClasses(string $layer): array
{
    $classes = [];

    foreach (contexts() as $context) {
        foreach (glob(__DIR__."/../src/{$context}/{$layer}/*.php") ?: [] as $file) {
            /** @var class-string $class */
            $class = "Made\\CmsPackage\\{$context}\\{$layer}\\".basename($file, '.php');

            $classes[] = $class;
        }
    }

    return $classes;
}

/*
|--------------------------------------------------------------------------
| Login helpers
|--------------------------------------------------------------------------
*/

/**
 * Counts the hashing from here on: every hash goes through the bcrypt driver, also after its settings change.
 */
function countHashing(): CountingHasher
{
    $hasher = new CountingHasher(fn (): Hasher => Hash::driver('bcrypt'));
    Hash::extend('counting', fn (): CountingHasher => $hasher);
    config(['hashing.driver' => 'counting']);

    return $hasher;
}

/**
 * Puts a disk on "public" that keeps the options every write and copy hands it (RecordingAdapter), with its files on
 * the fake disk of the test. $config is the configuration of the disk itself, such as ['visibility' => 'private'],
 * which Laravel hands its Flysystem as the defaults of every write.
 *
 * @param  array<string, mixed>  $config
 */
function recordMediaDisk(array $config = []): RecordingAdapter
{
    $recording = new RecordingAdapter(Storage::fake('public', $config)->getAdapter());
    $defaults = array_intersect_key($config, array_flip(['visibility', 'directory_visibility', 'retain_visibility']));

    Storage::set('public', new FilesystemAdapter(new Flysystem($recording, $defaults), $recording, $config));

    return $recording;
}

/**
 * The permissions of a file or folder on a local disk, as "644".
 */
function permissionsOf(string $path): string
{
    clearstatcache(true, $path);

    return decoct(((int) fileperms($path)) & 0777);
}

/**
 * The value of the cookie a browser keeps after this editor logged in on it.
 */
function deviceCookieOf(Editor $editor): string
{
    return EditorDevice::value(RememberDevice::run($editor, LoginMethod::Code));
}

/*
|--------------------------------------------------------------------------
| Workbench helpers
|--------------------------------------------------------------------------
*/

/**
 * Serves the workbench site: its strip types, its page types, its views and its routes with Route::madeCms().
 */
function useWorkbenchSite(): void
{
    $workbench = __DIR__.'/../workbench';

    $strips = StripTypes::discover($workbench.'/app/Cms/Strips', 'Workbench\\App\\Cms\\Strips');
    app()->instance(StripTypes::class, $strips);
    app()->instance(PageTypes::class, PageTypes::discover($workbench.'/app/Cms/PageTypes', 'Workbench\\App\\Cms\\PageTypes', $strips));
    app('view')->addLocation($workbench.'/resources/views');

    Route::middleware('web')->group($workbench.'/routes/web.php');
}

/*
|--------------------------------------------------------------------------
| Route helpers
|--------------------------------------------------------------------------
*/

/**
 * Registers the page routes the way a site does: the macro as the last line of its web routes.
 */
function servePages(Closure|string|null $responder = null): Illuminate\Routing\Route
{
    $route = null;

    Route::middleware('web')->group(function () use ($responder, &$route): void {
        $route = Route::madeCms($responder ?? static fn (Page $page): string => 'Pagina: '.$page->title);
    });

    /** @var Illuminate\Routing\Route $route */
    return $route;
}

/**
 * Does what `php artisan route:cache` does and loads the result back, without a separate process.
 */
function cacheRoutes(): void
{
    $router = app('router');
    $routes = $router->getRoutes();
    $routes->refreshNameLookups();
    $routes->refreshActionLookups();

    foreach ($routes->getRoutes() as $route) {
        $route->prepareForSerialization();
    }

    $file = sys_get_temp_dir().'/made-cms-routes-'.Str::random(16).'.php';
    file_put_contents($file, '<?php return '.var_export($routes->compile(), true).';');

    try {
        /** @var array{compiled: array<mixed>, attributes: array<string, mixed>} $compiled */
        $compiled = require $file;
        $router->setCompiledRoutes($compiled);
    } finally {
        unlink($file);
    }

    expect($router->getRoutes())->toBeInstanceOf(CompiledRouteCollection::class);
}

/*
|--------------------------------------------------------------------------
| Browser helpers
|--------------------------------------------------------------------------
*/

/**
 * A script for $page->script() that resolves to true once the JavaScript condition holds. Browser assertions do not
 * wait for it; use this after an animation or a reload.
 *
 * Pest gives every attempt of a script one second and tries again until the browser timeout when an attempt fails.
 * So one attempt watches for a little under a second and throws when the condition still does not hold: a slow
 * machine gets more attempts, a fast one is done at once, and a condition that never holds fails with its own text.
 */
function waitUntil(string $condition): string
{
    $quoted = json_encode($condition, JSON_THROW_ON_ERROR);

    return <<<JS
        () => new Promise((resolve, reject) => {
            const until = Date.now() + 800;
            const check = () => {
                if ({$condition}) {
                    resolve(true);
                } else if (Date.now() > until) {
                    reject(new Error('Still waiting for ' + {$quoted}));
                } else {
                    setTimeout(check, 50);
                }
            };
            check();
        })
        JS;
}

/**
 * A script for $page->script() that resolves to the value of the JavaScript expression after a moment, within a
 * single attempt: to check that something does not happen, such as a navigation.
 */
function valueAfter(string $expression, int $milliseconds = 800): string
{
    return <<<JS
        () => new Promise((resolve) => setTimeout(() => resolve({$expression}), {$milliseconds}))
        JS;
}

/**
 * The edit shell of a page with two strips, the strip chosen and its fields in the panel.
 */
function openStrip(Page $page, string $strip, string $ready): mixed
{
    $shell = visit('/made-cms/pages/'.$page->id.'/edit');

    expect($shell->script(waitUntil("document.querySelector('.bewerkframe').contentDocument?.querySelector('made-cms-outlines')?.shadowRoot?.querySelectorAll('.omlijning').length === 2")))->toBeTrue();

    // The tab with the name only chooses the strip; a click elsewhere on it opens the field under the pointer.
    $shell->withinFrame('.bewerkframe', fn ($frame) => $frame->click('[data-strip="'.$strip.'"] .tab'));

    expect($shell->script(waitUntil($ready)))->toBeTrue();

    return $shell;
}

/**
 * A JavaScript expression for the document of the site in the frame of the edit shell.
 */
function siteInFrame(): string
{
    return "document.querySelector('.bewerkframe').contentDocument";
}

/**
 * A script that marks the window in the frame: the mark is gone once the frame has reloaded.
 */
function markFrame(): string
{
    return "() => { document.querySelector('.bewerkframe').contentWindow.voorHerladen = true; }";
}

/**
 * A JavaScript condition: the frame reloaded since markFrame().
 */
function frameReloaded(): string
{
    return "! document.querySelector('.bewerkframe').contentWindow.voorHerladen";
}

/**
 * A JavaScript condition: the panel says the draft is saved.
 */
function savedAsDraft(): string
{
    return "document.querySelector('[data-slot=\"status\"]').textContent === 'Opgeslagen als concept'";
}

/**
 * One strip of the working copy of the page (the draft, else the published strips; a draft that a change took back
 * to the published page falls away), as it is stored now.
 *
 * @return array<string, mixed>
 */
function draftOf(Page $page, string $strip): array
{
    $stored = Page::query()->findOrFail($page->id);
    $draft = collect($stored->draft_strips ?? $stored->strips)->firstWhere('id', $strip);

    return is_array($draft) ? $draft : [];
}

/*
|--------------------------------------------------------------------------
| Strip helpers
|--------------------------------------------------------------------------
*/

/**
 * A JavaScript condition: the picker is open and shows these files, in this order.
 *
 * @param  list<string>  $names
 */
function pickerShows(array $names): string
{
    $quoted = json_encode($names, JSON_THROW_ON_ERROR);

    return "document.querySelector('[data-media-picker]').open && ! document.querySelector('[data-media]').hasAttribute('data-loading') && JSON.stringify([...document.querySelectorAll('[data-media-picker] .tegel [data-slot=\"media-tile-name\"]')].map((name) => name.textContent)) === JSON.stringify({$quoted})";
}

/**
 * A script for $page->script(): every folder of the picker with its number and whether it is open.
 */
function pickerFolders(): string
{
    return <<<'JS'
        () => [...document.querySelectorAll('[data-media-picker] .kmap')].map((folder) => [
            folder.querySelector('.kmap-naam').textContent.trim(),
            folder.querySelector('.kmap-aantal').textContent,
            folder.getAttribute('aria-pressed'),
        ])
        JS;
}

/**
 * The selector of the tile of a file in the picker.
 */
function pickerTile(Media $media): string
{
    return '[data-media-picker] .tegel[data-id="'.$media->id.'"]';
}

/**
 * The card of a strip type in the catalogue of the opbouw, by its name: the catalogue lists the types by label, so a
 * new type of the workbench never moves the card a test clicks.
 */
function catalogueCard(string $name): string
{
    return '[data-slot="catalogue-types"] .soortkaart:has([data-slot="type-name"]:text-is("'.$name.'"))';
}

/**
 * Binds a catalogue with the fixture strip types "hero" and "cards".
 */
function useStripTypes(): StripTypes
{
    $catalogue = new StripTypes([new Hero, new Cards]);

    app()->instance(StripTypes::class, $catalogue);

    return $catalogue;
}

/**
 * Binds a catalogue with the fixture page types "team" (hero and cards, starting with both) and "landing" (every
 * strip type, empty), next to the fixture strip types.
 */
function usePageTypes(): PageTypes
{
    $catalogue = new PageTypes([new TeamPage, new LandingPage], useStripTypes());

    app()->instance(PageTypes::class, $catalogue);

    return $catalogue;
}

/**
 * Binds a catalogue of the given page types, checked against the strip types bound now.
 */
function usePageTypesWith(PageType ...$types): PageTypes
{
    $catalogue = new PageTypes(array_values($types), app(StripTypes::class));

    app()->instance(PageTypes::class, $catalogue);

    return $catalogue;
}

/**
 * Points the strip types of the site at a folder and a namespace of their own, empty, for the classes the scan
 * writes. The folder is removed after the test.
 *
 * @return array{folder: string, namespace: string}
 */
function useStripFolder(): array
{
    $folder = sys_get_temp_dir().'/made-cms-strips-'.Str::random(8);
    $namespace = 'Made\\CmsPackage\\Tests\\Generated\\Run'.Str::random(8);

    config(['made-cms-package.strips.path' => $folder, 'made-cms-package.strips.namespace' => $namespace]);
    app()->instance(StripTypes::class, StripTypes::discover($folder, $namespace));

    test()->beforeApplicationDestroyed(static function () use ($folder): void {
        (new Filesystem)->deleteDirectory($folder);
    });

    return ['folder' => $folder, 'namespace' => $namespace];
}

/**
 * The catalogue of the classes the scan wrote, loaded as the site would load them.
 */
function writtenStripTypes(GeneratedStripTypes $result): StripTypes
{
    foreach ($result->written as $type) {
        require_once $type->path;
    }

    return StripTypes::discover(config()->string('made-cms-package.strips.path'), config()->string('made-cms-package.strips.namespace'));
}

/**
 * A strip in the form it is stored in cms_pages.
 *
 * @param  array<string, mixed>  $values
 * @return array{id: string, type: string, visible: bool, values: array<string, mixed>}
 */
function storedStrip(string $type = 'cards', array $values = [], bool $visible = true, ?string $id = null): array
{
    return [
        'id' => $id ?? (string) Str::uuid(),
        'type' => $type,
        'visible' => $visible,
        'values' => $values,
    ];
}

/**
 * Sends the address exactly as typed. The test client of Laravel trims a trailing slash and turns "//over-ons" into
 * "/over-ons" before the request is made, so $this->get() cannot ask for those addresses.
 *
 * @return TestResponse<Response>
 */
function requestAsTyped(string $uri): TestResponse
{
    $kernel = app(Kernel::class);
    $request = Request::create('http://localhost'.$uri);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return TestResponse::fromBaseResponse($response, $request);
}

/*
|--------------------------------------------------------------------------
| Traffic helpers
|--------------------------------------------------------------------------
*/

/**
 * The user agent of a person in a browser.
 */
function aBrowser(): string
{
    return 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15';
}

/**
 * The user agent Googlebot calls itself by.
 */
function googlebot(): string
{
    return 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
}

/*
|--------------------------------------------------------------------------
| Scan helpers
|--------------------------------------------------------------------------
*/

/**
 * A rendered page around the given body, as the browser hands it to the scan.
 */
function scanDocument(string $body, string $title = 'Testpagina'): string
{
    return '<!doctype html><html><head><meta charset="utf-8"><title>'.$title.'</title></head><body>'.$body.'</body></html>';
}

/**
 * What the scan reads on a page with the given body.
 */
function recognise(string $body, string $url = '/'): RecognisedPage
{
    return RecognisePage::run(new ScannedPage($url, scanDocument($body)));
}

/**
 * The fields the scan reads in the only strip of a page, by selector: the role, the value and the doubts if any.
 *
 * @return array<string, list<mixed>>
 */
function fieldsOf(string $strip): array
{
    $fields = [];

    foreach (recognise('<main>'.$strip.'</main>')->strips[0]->fields ?? [] as $field) {
        $fields[$field->selector] = fieldRow($field);
    }

    return $fields;
}

/**
 * @return list<mixed>
 */
function fieldRow(RecognisedField $field): array
{
    $row = [$field->role->value, $field->value];

    return $field->doubts === [] ? $row : [...$row, ScanDoubt::values($field->doubts)];
}

/**
 * The fields of a type as "key: type", with the fields of a list behind it.
 *
 * @return list<string|array<string, list<string>>>
 */
function outline(ScanType $type): array
{
    return array_map(static fn (ScanField $field): string|array => $field->type === 'items'
        ? [$field->key.': items' => array_map(static fn (ScanField $item): string => $item->key.': '.$item->type, $field->fields)]
        : $field->key.': '.$field->type, $type->fields);
}

/**
 * The strip types a scan proposes as a catalogue: the catalogue checks every definition when it is built.
 */
function scanStripTypes(ScanResult $scan): StripTypes
{
    return new StripTypes(array_map(ScannedStripType::from(...), $scan->types));
}

/**
 * The class files the scan proposes for its strip types, by name, as one text for a snapshot.
 *
 * @return array<string, string> file name => source
 */
function scanSources(ScanResult $scan, string $namespace = 'App\\Cms\\Strips'): array
{
    $sources = [];

    foreach ($scan->types as $type) {
        $class = StripTypeSource::className($type->key);
        $sources[$class.'.php'] = StripTypeSource::of(ScannedStripType::from($type), $namespace, $class);
    }

    ksort($sources);

    return $sources;
}

/**
 * Class files as one text, each behind a line with its name: one snapshot per test, also on Pest 4.
 *
 * @param  array<string, string>  $sources
 */
function sourcesText(array $sources): string
{
    $text = '';

    foreach ($sources as $file => $source) {
        $text .= "// ---- {$file}\n{$source}";
    }

    return $text;
}

/**
 * What Pint (Laravel preset) would change in the class files, with its exit code; an empty text when it leaves them as
 * they are. They are checked in a folder of their own, so the Pint configuration of the package does not count, and a
 * refusal shows what Pint said, also on a runner where it fails for another reason than the style.
 *
 * @param  array<string, string>  $sources
 */
function pintComplaints(array $sources): string
{
    $folder = sys_get_temp_dir().'/made-cms-pint-'.Str::random(8);
    mkdir($folder);

    try {
        foreach ($sources as $file => $source) {
            file_put_contents($folder.'/'.$file, $source);
        }

        // The plain text report, not the summary Pint renders as a Blade view: Pint 1.32.2 no longer finds that view in
        // its phar on Windows and fails after checking the files. The exit code says the same either way.
        $process = new Process([PHP_BINARY, __DIR__.'/../vendor/laravel/pint/builds/pint', '--test', '--preset=laravel', '--format=txt', '.'], $folder);
        $process->run();

        return $process->getExitCode() === 0
            ? ''
            : "Pint exited with {$process->getExitCode()}:\n".$process->getOutput().$process->getErrorOutput();
    } finally {
        foreach (glob($folder.'/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($folder);
    }
}

/**
 * The result of a scan as readable JSON, for a snapshot.
 */
function scanJson(ScanResult $scan): string
{
    return json_encode($scan->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
}

/*
|--------------------------------------------------------------------------
| TTV Merwestad fixture
|--------------------------------------------------------------------------
*/

/**
 * A file of the TTV Merwestad home, coupled by hand in #17: the acceptance test of the scan.
 */
function ttvFixture(string $file): string
{
    return __DIR__.'/Fixtures/Scan/ttv-merwestad/'.$file;
}

/**
 * @return array{path: string, title: string, strips: list<array{type: string, visible: bool, values: array<string, mixed>}>}
 */
function ttvContent(): array
{
    /** @var array{path: string, title: string, strips: list<array{type: string, visible: bool, values: array<string, mixed>}>} */
    return json_decode((string) file_get_contents(ttvFixture('content.json')), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * The scan of the live home (home.html), before the coupling.
 */
function ttvScan(): ScanResult
{
    $html = (string) file_get_contents(ttvFixture('home.html'));

    return CombinePageScans::run([RecognisePage::run(new ScannedPage('/', $html))]);
}

/**
 * The strip types the developer wrote for TTV, loaded from the stubs.
 *
 * The stubs keep the namespace of the site (App\Cms\Strips), but they load under a namespace of
 * their own: declaring App\Cms\Strips classes would leak into every test that runs after this one
 * in the same process, such as the discovery tests that expect that namespace to be empty.
 */
function ttvStripTypes(): StripTypes
{
    $namespace = 'Made\\CmsPackage\\Tests\\Fixtures\\Scan\\TtvMerwestad';
    $types = [];

    foreach (glob(ttvFixture('strips/*.php.stub')) ?: [] as $stub) {
        $class = $namespace.'\\'.basename($stub, '.php.stub');

        if (! class_exists($class, false)) {
            $source = (string) file_get_contents($stub);
            expect($source)->toContain('namespace App\\Cms\\Strips;');

            $file = (string) tempnam(sys_get_temp_dir(), 'made-cms-ttv-');
            file_put_contents($file, str_replace('namespace App\\Cms\\Strips;', "namespace {$namespace};", $source));

            try {
                require $file;
            } finally {
                unlink($file);
            }
        }

        if (is_subclass_of($class, StripType::class)) {
            $types[] = new $class;
        }
    }

    return new StripTypes($types);
}

/**
 * A file to upload as the browser hands it over: in a folder of its own, removed after the test.
 */
function uploadable(string $name, string $contents): string
{
    $folder = sys_get_temp_dir().'/made-cms-upload-'.Str::random(8);
    (new Filesystem)->ensureDirectoryExists($folder);
    (new Filesystem)->put("{$folder}/{$name}", $contents);

    test()->beforeApplicationDestroyed(static function () use ($folder): void {
        (new Filesystem)->deleteDirectory($folder);
    });

    return "{$folder}/{$name}";
}

/**
 * A photo of a size in a format GD writes (jpg, png, webp or gif), to upload. A jpg can carry an EXIF orientation,
 * such as 6 for a camera held a quarter turn clockwise.
 */
function photo(int $width, int $height, string $format = 'jpg', ?string $name = null, ?int $orientation = null): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height, (int) imagecolorallocate($image, 28, 61, 90));

    return photoFile($image, $format, $name, $orientation);
}

/** The colours of the quarters of quadrants(), by name. */
const QUADRANT_COLOURS = ['red' => [200, 40, 40], 'green' => [40, 160, 60], 'blue' => [40, 60, 200], 'yellow' => [230, 200, 40]];

/**
 * A photo in four coloured quarters, to upload: red at the top left, green at the top right, blue at the bottom left
 * and yellow at the bottom right, so a test sees how it was turned and mirrored (quadrantsOf()).
 */
function quadrants(int $width, int $height, string $format = 'jpg', ?string $name = null, ?int $orientation = null): string
{
    $image = imagecreatetruecolor($width, $height);
    $halfWidth = intdiv($width, 2);
    $halfHeight = intdiv($height, 2);

    foreach ([[0, 0, 'red'], [$halfWidth, 0, 'green'], [0, $halfHeight, 'blue'], [$halfWidth, $halfHeight, 'yellow']] as [$x, $y, $colour]) {
        [$red, $green, $blue] = QUADRANT_COLOURS[$colour];
        imagefilledrectangle($image, $x, $y, $x === 0 ? $halfWidth - 1 : $width - 1, $y === 0 ? $halfHeight - 1 : $height - 1, (int) imagecolorallocate($image, $red, $green, $blue));
    }

    return photoFile($image, $format, $name, $orientation);
}

/**
 * The colour names in the middle of the four quarters of an image, top left, top right, bottom left, bottom right;
 * "black" or "other" for a colour that is none of quadrants().
 *
 * @return list<string>
 */
function quadrantsOf(string $contents): array
{
    $image = imagecreatefromstring($contents);

    expect($image)->not->toBeFalse();
    assert($image instanceof GdImage);

    $width = imagesx($image);
    $height = imagesy($image);

    return array_map(static function (array $point) use ($image): string {
        $colour = imagecolorat($image, $point[0], $point[1]);
        $rgb = [($colour >> 16) & 0xFF, ($colour >> 8) & 0xFF, $colour & 0xFF];

        foreach ([...QUADRANT_COLOURS, 'black' => [0, 0, 0], 'photo' => [28, 61, 90]] as $name => $expected) {
            if (max(array_map(static fn (int $a, int $b): int => abs($a - $b), $rgb, $expected)) <= 40) {
                return $name;
            }
        }

        return 'other';
    }, [
        [intdiv($width, 4), intdiv($height, 4)],
        [intdiv($width * 3, 4), intdiv($height, 4)],
        [intdiv($width, 4), intdiv($height * 3, 4)],
        [intdiv($width * 3, 4), intdiv($height * 3, 4)],
    ]);
}

/**
 * An image of GD as a file to upload, with an EXIF orientation when given.
 */
function photoFile(GdImage $image, string $format, ?string $name, ?int $orientation): string
{
    ob_start();
    match ($format) {
        'jpg' => imagejpeg($image, null, 80),
        'png' => imagepng($image),
        'webp' => imagewebp($image),
        'gif' => imagegif($image),
    };
    $contents = (string) ob_get_clean();

    if ($orientation !== null) {
        $tiff = "MM\x00\x2A\x00\x00\x00\x08".pack('n', 1).pack('nnN', 0x0112, 3, 1).pack('n', $orientation)."\x00\x00".pack('N', 0);
        $exif = "Exif\x00\x00".$tiff;
        $contents = substr($contents, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($contents, 2);
    }

    return uploadable($name ?? "foto.{$format}", $contents);
}

/**
 * A gif of two frames that keeps moving, to upload.
 */
function animatedGif(string $name = 'laden.gif'): string
{
    $animation = ImageManager::usingDriver(Driver::class)
        ->createImage(40, 30, function (AnimationFactoryInterface $animation): void {
            $animation->add('1c3d5a', 0.2);
            $animation->add('f2b134', 0.2);
        });

    return uploadable($name, (string) $animation->encodeUsingFormat(Format::GIF));
}

/**
 * A file of tests/Fixtures/Media, to upload under its own name or another one.
 */
function mediaFixture(string $fixture, ?string $name = null): string
{
    return uploadable($name ?? $fixture, (string) file_get_contents(__DIR__."/Fixtures/Media/{$fixture}"));
}
