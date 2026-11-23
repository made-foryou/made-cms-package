<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Made\CmsPackage\Content\Models\Page;

function pageTypeMigration(): Migration
{
    return require __DIR__.'/../../../database/migrations/2026_11_23_000100_add_type_to_cms_pages_table.php';
}

it('adds the type of a page and takes it away again', function (): void {
    pageTypeMigration()->down();

    expect(Schema::hasColumn('cms_pages', 'type'))->toBeFalse();

    pageTypeMigration()->up();

    $column = collect(Schema::getColumns('cms_pages'))->firstWhere('name', 'type');

    expect($column['nullable'] ?? null)->toBeTrue();
});

it('makes a page that was there already an ordinary page', function (): void {
    pageTypeMigration()->down();
    $id = DB::table('cms_pages')->insertGetId([
        'title' => 'Over ons',
        'slug' => 'over-ons',
        'strips' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    pageTypeMigration()->up();

    expect(DB::table('cms_pages')->find($id)?->type)->toBeNull();
});

it('keeps the type of a page', function (): void {
    $page = Page::factory()->create(['type' => 'team']);

    expect(Page::query()->findOrFail($page->id)->type)->toBe('team');
});

it('leaves a database that has the column already as it is', function (): void {
    pageTypeMigration()->up();

    expect(Schema::hasColumn('cms_pages', 'type'))->toBeTrue();
});
