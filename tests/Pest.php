<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Made\CmsPackage\Content\Actions\RecognisePage;
use Made\CmsPackage\Content\Data\RecognisedField;
use Made\CmsPackage\Content\Data\RecognisedPage;
use Made\CmsPackage\Content\Data\ScannedPage;
use Made\CmsPackage\Content\Enums\ScanDoubt;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Hero;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Nested\Cards;
use Made\CmsPackage\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

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
 * A JavaScript condition: the panel says the draft is saved.
 */
function savedAsDraft(): string
{
    return "document.querySelector('[data-slot=\"status\"]').textContent === 'Opgeslagen als concept'";
}

/**
 * The draft values of one strip of the page, as they are stored now.
 *
 * @return array<string, mixed>
 */
function draftOf(Page $page, string $strip): array
{
    $draft = collect(Page::query()->findOrFail($page->id)->draft_strips ?? [])->firstWhere('id', $strip);

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
