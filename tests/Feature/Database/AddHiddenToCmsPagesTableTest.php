<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function hiddenMigration(): Migration
{
    return require __DIR__.'/../../../database/migrations/2026_11_19_000100_add_hidden_to_cms_pages_table.php';
}

it('adds whether visitors see a page and takes it away again', function (): void {
    hiddenMigration()->down();

    expect(Schema::hasColumn('cms_pages', 'hidden'))->toBeFalse();

    hiddenMigration()->up();

    expect(Schema::hasColumn('cms_pages', 'hidden'))->toBeTrue();
});

it('keeps a page that was there already visible to visitors', function (): void {
    hiddenMigration()->down();
    $id = DB::table('cms_pages')->insertGetId([
        'title' => 'Privacy',
        'slug' => 'privacy',
        'strips' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    hiddenMigration()->up();

    expect((bool) DB::table('cms_pages')->find($id)?->hidden)->toBeFalse();
});

it('leaves a database that has the column already as it is', function (): void {
    hiddenMigration()->up();

    expect(Schema::hasColumn('cms_pages', 'hidden'))->toBeTrue();
});
