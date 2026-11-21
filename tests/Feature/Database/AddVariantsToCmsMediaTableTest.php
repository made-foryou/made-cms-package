<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Made\CmsPackage\Content\Models\Media;

function variantsMigration(): Migration
{
    return require __DIR__.'/../../../database/migrations/2026_11_21_000000_add_variants_to_cms_media_table.php';
}

it('adds the narrower sizes of a photo and takes them away again', function (): void {
    variantsMigration()->down();

    expect(Schema::hasColumn('cms_media', 'variants'))->toBeFalse();

    variantsMigration()->up();

    expect(collect(Schema::getColumns('cms_media'))->firstWhere('name', 'variants')['nullable'] ?? null)->toBeTrue();
});

it('gives a file that was there already no sizes yet, so the command makes them', function (): void {
    variantsMigration()->down();
    $media = Media::factory()->make();
    $id = Schema::getConnection()->table('cms_media')->insertGetId(collect($media->getAttributes())->except('variants')->all());

    variantsMigration()->up();

    expect(Media::query()->findOrFail($id)->variants)->toBeNull();
});

it('leaves a database that has the column already as it is', function (): void {
    variantsMigration()->up();

    expect(Schema::hasColumn('cms_media', 'variants'))->toBeTrue();
});
