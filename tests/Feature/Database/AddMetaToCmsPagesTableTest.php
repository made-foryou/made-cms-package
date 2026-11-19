<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function metaMigration(): Migration
{
    return require __DIR__.'/../../../database/migrations/2026_11_19_000000_add_meta_to_cms_pages_table.php';
}

it('adds the google fields of a page and takes them away again', function (): void {
    $columns = ['meta_title', 'meta_description', 'in_sitemap', 'indexable'];

    metaMigration()->down();

    expect(Schema::hasColumns('cms_pages', $columns))->toBeFalse();

    metaMigration()->up();

    expect(Schema::hasColumns('cms_pages', $columns))->toBeTrue();
});

it('lets a page that was there already stay in the sitemap and in google, without a title or description of its own', function (): void {
    metaMigration()->down();
    $id = DB::table('cms_pages')->insertGetId([
        'title' => 'Onze teams',
        'slug' => 'onze-teams',
        'strips' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    metaMigration()->up();
    $row = DB::table('cms_pages')->find($id);

    expect($row?->meta_title)->toBeNull()
        ->and($row?->meta_description)->toBeNull()
        ->and((bool) $row?->in_sitemap)->toBeTrue()
        ->and((bool) $row?->indexable)->toBeTrue();
});

it('leaves a database that has the columns already as it is', function (): void {
    metaMigration()->up();

    expect(Schema::hasColumns('cms_pages', ['meta_title', 'indexable']))->toBeTrue();
});
