<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function addressHitsMigration(): Migration
{
    return require __DIR__.'/../../../database/migrations/2026_11_28_000000_create_cms_address_hits_table.php';
}

it('creates the table of the counts of the addresses and takes it away again', function (): void {
    $columns = ['day', 'prefix', 'path', 'outcome', 'source', 'referrer_path', 'hits'];

    expect(Schema::hasColumns('cms_address_hits', $columns))->toBeTrue();

    addressHitsMigration()->down();

    expect(Schema::hasTable('cms_address_hits'))->toBeFalse();

    addressHitsMigration()->up();

    expect(Schema::hasColumns('cms_address_hits', $columns))->toBeTrue();
});

it('keeps no ip address, user agent or moment of a visit, only counts per day', function (): void {
    expect(Schema::getColumnListing('cms_address_hits'))
        ->toEqualCanonicalizing(['id', 'day', 'prefix', 'path', 'outcome', 'source', 'referrer_path', 'hits']);
});

it('holds one count per address, day, outcome, source and page it came from', function (): void {
    $row = [
        'day' => '2026-11-28',
        'prefix' => '',
        'path' => '/lid-worden-oud',
        'outcome' => 'not_found',
        'source' => 'google',
        'referrer_path' => '',
        'hits' => 1,
    ];

    DB::table('cms_address_hits')->insert($row);
    DB::table('cms_address_hits')->insert([...$row, 'source' => 'direct']);

    DB::table('cms_address_hits')->insert($row);
})->throws(QueryException::class);
