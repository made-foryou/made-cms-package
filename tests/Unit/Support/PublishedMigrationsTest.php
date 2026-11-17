<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Made\CmsPackage\Support\PublishedMigrations;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/made-cms-migrations-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($this->root.'/package');
    File::ensureDirectoryExists($this->root.'/host');

    foreach (['2026_01_01_000200_create_cms_media_folders_table.php', '2026_01_01_000250_create_cms_media_table.php', '2026_11_17_000000_add_versions_to_cms_media_table.php'] as $name) {
        File::put($this->root.'/package/'.$name, '<?php // '.$name);
    }
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
});

it('gives a migration the host does not have the date of the moment, one second after the other in the order of the package', function (): void {
    $paths = PublishedMigrations::paths($this->root.'/package', $this->root.'/host', CarbonImmutable::parse('2026-11-18 09:30:00'));

    expect($paths)->toBe([
        $this->root.'/package/2026_01_01_000200_create_cms_media_folders_table.php' => $this->root.'/host/2026_11_18_093001_create_cms_media_folders_table.php',
        $this->root.'/package/2026_01_01_000250_create_cms_media_table.php' => $this->root.'/host/2026_11_18_093002_create_cms_media_table.php',
        $this->root.'/package/2026_11_17_000000_add_versions_to_cms_media_table.php' => $this->root.'/host/2026_11_18_093003_add_versions_to_cms_media_table.php',
    ]);
});

it('points a migration the host has already, under any date, at that file', function (): void {
    File::put($this->root.'/host/2026_11_10_141502_create_cms_media_folders_table.php', 'published');
    File::put($this->root.'/host/2026_01_01_000250_create_cms_media_table.php', 'published before the dates moved');
    File::put($this->root.'/host/2014_10_12_000000_create_users_table.php', 'the host');

    $paths = PublishedMigrations::paths($this->root.'/package', $this->root.'/host', CarbonImmutable::parse('2026-11-18 09:30:00'));

    expect($paths)->toBe([
        $this->root.'/package/2026_01_01_000200_create_cms_media_folders_table.php' => $this->root.'/host/2026_11_10_141502_create_cms_media_folders_table.php',
        $this->root.'/package/2026_01_01_000250_create_cms_media_table.php' => $this->root.'/host/2026_01_01_000250_create_cms_media_table.php',
        $this->root.'/package/2026_11_17_000000_add_versions_to_cms_media_table.php' => $this->root.'/host/2026_11_18_093001_add_versions_to_cms_media_table.php',
    ]);
});

it('keeps to the first copy when the host has a migration twice', function (): void {
    File::put($this->root.'/host/2026_11_10_141502_create_cms_media_table.php', 'first');
    File::put($this->root.'/host/2026_11_12_080000_create_cms_media_table.php', 'second');

    $paths = PublishedMigrations::paths($this->root.'/package', $this->root.'/host', CarbonImmutable::parse('2026-11-18 09:30:00'));

    expect($paths[$this->root.'/package/2026_01_01_000250_create_cms_media_table.php'])->toBe($this->root.'/host/2026_11_10_141502_create_cms_media_table.php');
});

it('takes no file of the host for another migration whose name merely ends the same', function (): void {
    File::put($this->root.'/host/2026_11_10_141502_old_create_cms_media_table.php', 'another migration');

    $paths = PublishedMigrations::paths($this->root.'/package', $this->root.'/host', CarbonImmutable::parse('2026-11-18 09:30:00'));

    expect($paths[$this->root.'/package/2026_01_01_000250_create_cms_media_table.php'])->toBe($this->root.'/host/2026_11_18_093002_create_cms_media_table.php');
});

it('publishes everything new to a host without a folder of migrations yet', function (): void {
    File::deleteDirectory($this->root.'/host');

    expect(PublishedMigrations::paths($this->root.'/package', $this->root.'/host', CarbonImmutable::parse('2026-11-18 09:30:00')))->toHaveCount(3);
});
