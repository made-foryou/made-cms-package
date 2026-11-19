<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\Page;

function shareImageMigration(): Migration
{
    return require __DIR__.'/../../../database/migrations/2026_11_19_000300_add_share_image_to_cms_pages_table.php';
}

it('adds the share image of a page and takes it away again', function (): void {
    shareImageMigration()->down();

    expect(Schema::hasColumn('cms_pages', 'share_image_id'))->toBeFalse()
        ->and(collect(Schema::getForeignKeys('cms_pages'))->firstWhere('columns', ['share_image_id']))->toBeNull();

    shareImageMigration()->up();

    $column = collect(Schema::getColumns('cms_pages'))->firstWhere('name', 'share_image_id');
    $key = collect(Schema::getForeignKeys('cms_pages'))->firstWhere('columns', ['share_image_id']);

    expect($column['nullable'] ?? null)->toBeTrue()
        ->and($key['foreign_table'] ?? null)->toBe('cms_media')
        ->and($key['on_delete'] ?? null)->toBe('restrict');
});

it('gives a page that was there already no share image', function (): void {
    shareImageMigration()->down();
    $id = DB::table('cms_pages')->insertGetId([
        'title' => 'Over ons',
        'slug' => 'over-ons',
        'strips' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    shareImageMigration()->up();

    expect(DB::table('cms_pages')->find($id)?->share_image_id)->toBeNull();
});

it('keeps a file a page shares as its image', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->withShareImage($media)->create();

    expect(Page::query()->findOrFail($page->id)->share_image_id)->toBe($media->id);

    $media->delete();
})->throws(QueryException::class);

it('leaves a database that has the column already as it is', function (): void {
    shareImageMigration()->up();

    expect(Schema::hasColumn('cms_pages', 'share_image_id'))->toBeTrue();
});
