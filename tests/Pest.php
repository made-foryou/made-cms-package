<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Made\CmsPackage\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Suites
|--------------------------------------------------------------------------
|
| Feature and Unit tests boot the package through Testbench. Feature tests
| also get a fresh in-memory database. Arch tests stay on the plain PHPUnit
| base: they only inspect source files and never boot the application.
|
*/

pest()->extend(TestCase::class)->in('Unit');

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

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
| Content folders
|--------------------------------------------------------------------------
*/

/**
 * Points the strip and page folders of the content config at a fresh temporary folder, filled with the example
 * host files of tests/Fixtures/content unless $empty. Returns the root; delete it in afterEach.
 */
function useContentFolders(bool $empty = false): string
{
    $root = sys_get_temp_dir().'/made-cms-content-'.Str::random(12);

    File::ensureDirectoryExists("{$root}/strips");
    File::ensureDirectoryExists("{$root}/pages");

    if (! $empty) {
        File::copyDirectory(__DIR__.'/Fixtures/content', $root);
    }

    config()->set('made-cms-package.content.strips_path', "{$root}/strips");
    config()->set('made-cms-package.content.pages_path', "{$root}/pages");

    return $root;
}
