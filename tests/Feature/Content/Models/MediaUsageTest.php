<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Made\CmsPackage\Content\Enums\PagePlace;
use Made\CmsPackage\Content\Enums\UsageVersion;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

it('reads a stored place in its own types', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->create();

    $usage = MediaUsage::factory()->for($media)->for($page)->draft()->create([
        'strip_id' => 'strip-1',
        'strip_type' => 'cards',
        'field' => 'kaarten.2.icoon',
    ]);

    $stored = MediaUsage::query()->findOrFail($usage->id);

    expect($stored->media_id)->toBe($media->id)
        ->and($stored->page_id)->toBe($page->id)
        ->and($stored->version)->toBe(UsageVersion::Draft)
        ->and($stored->strip_id)->toBe('strip-1')
        ->and($stored->strip_type)->toBe('cards')
        ->and($stored->field)->toBe('kaarten.2.icoon')
        ->and($stored->media?->is($media))->toBeTrue()
        ->and($stored->page?->is($page))->toBeTrue()
        ->and($media->usages()->pluck('id')->all())->toBe([$usage->id]);
});

it('reads a place of the page itself, outside its strips', function (): void {
    $page = Page::factory()->create();

    $usage = MediaUsage::factory()->for($page)->shareImage()->create();

    $stored = MediaUsage::query()->findOrFail($usage->id);

    expect($stored->version)->toBe(UsageVersion::Page)
        ->and($stored->strip_id)->toBe('')
        ->and($stored->strip_type)->toBe('')
        ->and($stored->field)->toBe(PagePlace::ShareImage->value);
});

it('holds the share image of a page once', function (): void {
    $page = Page::factory()->create();
    MediaUsage::factory()->for($page)->shareImage()->create();

    MediaUsage::factory()->for($page)->shareImage()->create();
})->throws(QueryException::class);

it('holds a place of a version of a page once', function (): void {
    $page = Page::factory()->create();
    MediaUsage::factory()->for($page)->create(['strip_id' => 'a', 'field' => 'foto']);

    MediaUsage::factory()->for($page)->create(['strip_id' => 'a', 'field' => 'foto']);
})->throws(QueryException::class);

it('holds the same place in the draft and in the published version', function (): void {
    $page = Page::factory()->create();

    MediaUsage::factory()->for($page)->create(['strip_id' => 'a', 'field' => 'foto']);
    MediaUsage::factory()->for($page)->draft()->create(['strip_id' => 'a', 'field' => 'foto']);

    expect(MediaUsage::query()->count())->toBe(2);
});

it('keeps a file that is still in use', function (): void {
    $usage = MediaUsage::factory()->create();

    $usage->media?->delete();
})->throws(QueryException::class);

it('keeps the places of a deleted page until it is removed for good', function (): void {
    $page = Page::factory()->create();
    MediaUsage::factory()->for($page)->create();

    $page->delete();

    expect(MediaUsage::query()->count())->toBe(1);

    $page->forceDelete();

    expect(MediaUsage::query()->count())->toBe(0);
});
