<?php

declare(strict_types=1);

use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Made\CmsPackage\Access\Actions\RememberDevice;
use Made\CmsPackage\Access\Enums\LoginMethod;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\CombinePageScans;
use Made\CmsPackage\Content\Actions\RecognisePage;
use Made\CmsPackage\Content\Data\GeneratedStripTypes;
use Made\CmsPackage\Content\Data\RecognisedField;
use Made\CmsPackage\Content\Data\RecognisedPage;
use Made\CmsPackage\Content\Data\ScanField;
use Made\CmsPackage\Content\Data\ScannedPage;
use Made\CmsPackage\Content\Data\ScanResult;
use Made\CmsPackage\Content\Data\ScanType;
use Made\CmsPackage\Content\Enums\ScanDoubt;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Scan\ScannedStripType;
use Made\CmsPackage\Content\Scan\StripTypeSource;
use Made\CmsPackage\Content\Strips\StripType;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Http\Cookies\EditorDevice;
use Made\CmsPackage\Tests\Fixtures\Hashing\CountingHasher;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Hero;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Nested\Cards;
use Made\CmsPackage\Tests\TestCase;
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
 * Serves the workbench site: its strip types, its views and its routes with Route::madeCms().
 */
function useWorkbenchSite(): void
{
    $workbench = __DIR__.'/../workbench';

    app()->instance(StripTypes::class, StripTypes::discover($workbench.'/app/Cms/Strips', 'Workbench\\App\\Cms\\Strips'));
    app('view')->addLocation($workbench.'/resources/views');

    Route::middleware('web')->group($workbench.'/routes/web.php');
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
 * Binds a catalogue with the fixture strip types "hero" and "cards".
 */
function useStripTypes(): StripTypes
{
    $catalogue = new StripTypes([new Hero, new Cards]);

    app()->instance(StripTypes::class, $catalogue);

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
 * Whether Pint (Laravel preset) leaves the class files as they are; they are checked in a folder of their own, so the
 * Pint configuration of the package does not count.
 *
 * @param  array<string, string>  $sources
 */
function pintAccepts(array $sources): bool
{
    $folder = sys_get_temp_dir().'/made-cms-pint-'.Str::random(8);
    mkdir($folder);

    try {
        foreach ($sources as $file => $source) {
            file_put_contents($folder.'/'.$file, $source);
        }

        $process = new Process([PHP_BINARY, __DIR__.'/../vendor/laravel/pint/builds/pint', '--test', '--preset=laravel', '.'], $folder);
        $process->run();

        return $process->getExitCode() === 0;
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
