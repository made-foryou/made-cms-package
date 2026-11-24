<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

function createdByMigration(): Migration
{
    return require __DIR__.'/../../../database/migrations/2026_11_23_000200_add_created_by_to_cms_pages_table.php';
}

it('adds who created a page and takes it away again', function (): void {
    createdByMigration()->down();

    expect(Schema::hasColumn('cms_pages', 'created_by'))->toBeFalse()
        ->and(collect(Schema::getForeignKeys('cms_pages'))->firstWhere('columns', ['created_by']))->toBeNull();

    createdByMigration()->up();

    $column = collect(Schema::getColumns('cms_pages'))->firstWhere('name', 'created_by');
    $key = collect(Schema::getForeignKeys('cms_pages'))->firstWhere('columns', ['created_by']);

    expect($column['nullable'] ?? null)->toBeTrue()
        ->and($key['foreign_table'] ?? null)->toBe('cms_editors')
        ->and($key['on_delete'] ?? null)->toBe('set null');
});

it('does not know who created a page that was there already', function (): void {
    createdByMigration()->down();
    $id = DB::table('cms_pages')->insertGetId([
        'title' => 'Over ons',
        'slug' => 'over-ons',
        'strips' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    createdByMigration()->up();

    expect(DB::table('cms_pages')->find($id)?->created_by)->toBeNull();
});

it('keeps the page when the editor who created it is removed', function (): void {
    $editor = Editor::factory()->create();
    $page = Page::factory()->create(['created_by' => $editor->id]);

    expect(Page::query()->findOrFail($page->id)->created_by)->toBe($editor->id);

    $editor->delete();

    expect(Page::query()->findOrFail($page->id)->created_by)->toBeNull();
});

it('refuses an editor that is not there', function (): void {
    Page::factory()->create(['created_by' => missingId()]);
})->throws(QueryException::class);

it('leaves a database that has the column already as it is', function (): void {
    createdByMigration()->up();

    expect(Schema::hasColumn('cms_pages', 'created_by'))->toBeTrue();
});
