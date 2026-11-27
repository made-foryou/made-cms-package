<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Builders\MediaUsageBuilder;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

it('uses the custom builder for usage queries', function (): void {
    expect(MediaUsage::query())->toBeInstanceOf(MediaUsageBuilder::class);
});

it('returns the places on a page, in both versions', function (): void {
    $page = Page::factory()->create();
    $published = MediaUsage::factory()->for($page)->create(['strip_id' => 'a']);
    $draft = MediaUsage::factory()->for($page)->draft()->create(['strip_id' => 'a']);
    MediaUsage::factory()->create();

    expect(MediaUsage::query()->onPage($page)->orderBy('id')->pluck('id')->all())->toBe([$published->id, $draft->id]);
});

it('counts a place in both versions of a page once', function (): void {
    $page = Page::factory()->create();
    MediaUsage::factory()->for($page)->create(['strip_id' => 'a', 'field' => 'foto']);
    MediaUsage::factory()->for($page)->draft()->create(['strip_id' => 'a', 'field' => 'foto']);
    MediaUsage::factory()->for($page)->draft()->create(['strip_id' => 'a', 'field' => 'logo']);
    MediaUsage::factory()->create(['strip_id' => 'a', 'field' => 'foto']);

    expect(MediaUsage::query()->placeCount())->toBe(3)
        ->and(MediaUsage::query()->onPage($page)->placeCount())->toBe(2)
        ->and(MediaUsage::query()->onPage(Page::factory()->create())->placeCount())->toBe(0);
});

it('returns the places of these files on any page, also a deleted one', function (): void {
    $page = Page::factory()->create();
    $here = MediaUsage::factory()->for($page)->create(['strip_id' => 'a']);
    $deleted = MediaUsage::factory()->for(Page::factory()->create(['deleted_at' => now()]))->draft()->create(['strip_id' => 'b']);
    MediaUsage::factory()->create();

    expect(MediaUsage::query()->ofMedia([$here->media_id, $deleted->media_id])->orderBy('id')->pluck('id')->all())->toBe([$here->id, $deleted->id]);
});

it('returns the places of images without an alt text, never of a document or a video', function (): void {
    $page = Page::factory()->create();
    $without = MediaUsage::factory()->for($page)->create(['strip_id' => 'a']);
    MediaUsage::factory()->for($page)->for(Media::factory()->withAlt('Het eerste team'))->create(['strip_id' => 'b']);
    MediaUsage::factory()->for($page)->for(Media::factory()->document())->create(['strip_id' => 'c']);
    MediaUsage::factory()->for($page)->for(Media::factory()->video())->create(['strip_id' => 'd']);

    expect(MediaUsage::query()->withoutAlt()->pluck('id')->all())->toBe([$without->id]);
});
