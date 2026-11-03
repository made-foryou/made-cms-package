<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Hero;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Nested\Cards;
use Made\CmsPackage\Tests\TestCase;

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
 * A script for $page->script() that resolves to true once the JavaScript condition holds,
 * or to false after the timeout. Browser assertions do not wait; use this after an animation.
 */
function waitUntil(string $condition, int $milliseconds = 5000): string
{
    return <<<JS
        () => new Promise((resolve) => {
            const until = Date.now() + {$milliseconds};
            const check = () => {
                if ({$condition}) {
                    resolve(true);
                } else if (Date.now() > until) {
                    resolve(false);
                } else {
                    setTimeout(check, 50);
                }
            };
            check();
        })
        JS;
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
