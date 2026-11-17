<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Made\CmsPackage\Tests\TestCase;

/**
 * The migrations of the host, by name.
 *
 * @return list<string>
 */
function hostMigrations(): array
{
    return array_map(basename(...), File::glob(database_path('migrations').'/*.php'));
}

// A host of its own: the application starts again on a database folder only this test writes into.
beforeEach(function (): void {
    TestCase::$databasePath = sys_get_temp_dir().'/made-cms-host-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists(TestCase::$databasePath.'/migrations');
    $this->refreshApplication();
    $this->before = hostMigrations();
});

afterEach(function (): void {
    File::deleteDirectory((string) TestCase::$databasePath);
    TestCase::$databasePath = null;
});

it('publishes the migrations once, and skips them when they are published again later', function (): void {
    $this->artisan('vendor:publish', ['--tag' => 'made-cms-package-migrations'])->assertSuccessful();

    $published = array_values(array_diff(hostMigrations(), $this->before));

    expect($published)->toHaveCount(count(File::glob(__DIR__.'/../../database/migrations/*.php')))
        ->and(preg_grep('/_create_cms_media_table\.php$/', $published))->toHaveCount(1);

    // Another day, another process: the migrations the host has keep their names and are not published again.
    $this->travel(1)->days();
    $this->refreshApplication();

    $this->artisan('vendor:publish', ['--tag' => 'made-cms-package-migrations'])->assertSuccessful();

    expect(array_values(array_diff(hostMigrations(), $this->before)))->toBe($published);
});

it('publishes only the migration the host does not have yet', function (): void {
    File::put(database_path('migrations/2026_11_10_141502_create_cms_media_table.php'), '<?php // as the host published it');
    $this->refreshApplication();

    $this->artisan('vendor:publish', ['--tag' => 'made-cms-package-migrations'])->assertSuccessful();

    $published = array_values(array_diff(hostMigrations(), $this->before));

    expect(preg_grep('/_create_cms_media_table\.php$/', $published))->toBe([array_search('2026_11_10_141502_create_cms_media_table.php', $published, true) => '2026_11_10_141502_create_cms_media_table.php'])
        ->and(File::get(database_path('migrations/2026_11_10_141502_create_cms_media_table.php')))->toBe('<?php // as the host published it')
        ->and(preg_grep('/_add_versions_to_cms_media_table\.php$/', $published))->toHaveCount(1);
});

it('overwrites a migration where the host has it with --force, never as a copy under a new date', function (): void {
    File::put(database_path('migrations/2026_11_10_141502_create_cms_media_table.php'), '<?php // as the host published it');
    $this->refreshApplication();

    $this->artisan('vendor:publish', ['--tag' => 'made-cms-package-migrations', '--force' => true])->assertSuccessful();

    expect(preg_grep('/_create_cms_media_table\.php$/', hostMigrations()))->toHaveCount(1)
        ->and(File::get(database_path('migrations/2026_11_10_141502_create_cms_media_table.php')))->toBe(File::get(__DIR__.'/../../database/migrations/2026_01_01_000250_create_cms_media_table.php'));
});
