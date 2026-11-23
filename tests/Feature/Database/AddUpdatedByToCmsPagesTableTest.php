<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

function updatedByMigration(): Migration
{
    return require __DIR__.'/../../../database/migrations/2026_11_23_000000_add_updated_by_to_cms_pages_table.php';
}

it('adds who changed a page last and takes it away again', function (): void {
    updatedByMigration()->down();

    expect(Schema::hasColumn('cms_pages', 'updated_by'))->toBeFalse()
        ->and(collect(Schema::getForeignKeys('cms_pages'))->firstWhere('columns', ['updated_by']))->toBeNull();

    updatedByMigration()->up();

    $column = collect(Schema::getColumns('cms_pages'))->firstWhere('name', 'updated_by');
    $key = collect(Schema::getForeignKeys('cms_pages'))->firstWhere('columns', ['updated_by']);

    expect($column['nullable'] ?? null)->toBeTrue()
        ->and($key['foreign_table'] ?? null)->toBe('cms_editors')
        ->and($key['on_delete'] ?? null)->toBe('set null');
});

it('does not know who changed a page that was there already', function (): void {
    updatedByMigration()->down();
    $id = DB::table('cms_pages')->insertGetId([
        'title' => 'Over ons',
        'slug' => 'over-ons',
        'strips' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    updatedByMigration()->up();

    expect(DB::table('cms_pages')->find($id)?->updated_by)->toBeNull();
});

it('keeps the page when the editor who changed it is removed', function (): void {
    $editor = Editor::factory()->create();
    $page = Page::factory()->create(['updated_by' => $editor->id]);

    expect(Page::query()->findOrFail($page->id)->updated_by)->toBe($editor->id);

    $editor->delete();

    expect(Page::query()->findOrFail($page->id)->updated_by)->toBeNull();
});

it('refuses an editor that is not there', function (): void {
    Page::factory()->create(['updated_by' => missingId()]);
})->throws(QueryException::class);

it('leaves a database that has the column already as it is', function (): void {
    updatedByMigration()->up();

    expect(Schema::hasColumn('cms_pages', 'updated_by'))->toBeTrue();
});
