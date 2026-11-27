<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

function publishAtMigration(): Migration
{
    return require __DIR__.'/../../../database/migrations/2026_11_27_000000_add_publish_at_to_cms_pages_table.php';
}

it('adds the planned publication of a page and takes it away again', function (): void {
    publishAtMigration()->down();

    expect(Schema::hasColumn('cms_pages', 'publish_at'))->toBeFalse()
        ->and(Schema::hasColumn('cms_pages', 'scheduled_by'))->toBeFalse()
        ->and(Schema::hasColumn('cms_pages', 'publish_failed_at'))->toBeFalse()
        ->and(collect(Schema::getForeignKeys('cms_pages'))->firstWhere('columns', ['scheduled_by']))->toBeNull();

    publishAtMigration()->up();

    $columns = collect(Schema::getColumns('cms_pages'))->keyBy('name');
    $key = collect(Schema::getForeignKeys('cms_pages'))->firstWhere('columns', ['scheduled_by']);
    $index = collect(Schema::getIndexes('cms_pages'))->firstWhere('columns', ['publish_at']);

    expect($columns['publish_at']['nullable'] ?? null)->toBeTrue()
        ->and($columns['scheduled_by']['nullable'] ?? null)->toBeTrue()
        ->and($columns['publish_failed_at']['nullable'] ?? null)->toBeTrue()
        ->and($key['foreign_table'] ?? null)->toBe('cms_editors')
        ->and($key['on_delete'] ?? null)->toBe('set null')
        ->and($index)->not->toBeNull();
});

it('plans nothing for a page that was there already', function (): void {
    publishAtMigration()->down();
    $id = DB::table('cms_pages')->insertGetId([
        'title' => 'Over ons',
        'slug' => 'over-ons',
        'strips' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    publishAtMigration()->up();

    $page = DB::table('cms_pages')->find($id);

    expect($page?->publish_at)->toBeNull()
        ->and($page?->scheduled_by)->toBeNull()
        ->and($page?->publish_failed_at)->toBeNull();
});

it('keeps the planned publication when the editor who planned it is removed', function (): void {
    $editor = Editor::factory()->create();
    $page = Page::factory()->scheduledAt(now()->addDay(), $editor)->create();

    $editor->delete();

    $page = Page::query()->findOrFail($page->id);

    expect($page->scheduled_by)->toBeNull()
        ->and($page->publish_at)->not->toBeNull();
});

it('refuses an editor that is not there', function (): void {
    Page::factory()->create(['scheduled_by' => missingId()]);
})->throws(QueryException::class);

it('leaves a database that has the columns already as it is', function (): void {
    publishAtMigration()->up();

    expect(Schema::hasColumns('cms_pages', ['publish_at', 'scheduled_by', 'publish_failed_at']))->toBeTrue();
});
