<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function notFoundPathsMigration(): Migration
{
    return require __DIR__.'/../../../database/migrations/2026_11_28_000100_create_cms_not_found_paths_table.php';
}

it('creates the table of the addresses without a page and takes it away again', function (): void {
    $columns = ['path', 'first_seen_at', 'last_seen_at', 'ignored_at'];

    expect(Schema::hasColumns('cms_not_found_paths', $columns))->toBeTrue();

    notFoundPathsMigration()->down();

    expect(Schema::hasTable('cms_not_found_paths'))->toBeFalse();

    notFoundPathsMigration()->up();

    expect(Schema::hasColumns('cms_not_found_paths', $columns))->toBeTrue();
});

it('keeps no ip address or user agent of the visitors of an address', function (): void {
    expect(Schema::getColumnListing('cms_not_found_paths'))
        ->toEqualCanonicalizing(['id', 'path', 'first_seen_at', 'last_seen_at', 'ignored_at']);
});

it('holds an address once', function (): void {
    $row = ['path' => '/wp-admin', 'first_seen_at' => now(), 'last_seen_at' => now()];

    DB::table('cms_not_found_paths')->insert($row);
    DB::table('cms_not_found_paths')->insert($row);
})->throws(QueryException::class);
