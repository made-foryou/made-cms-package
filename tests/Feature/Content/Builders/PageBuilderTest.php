<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Builders\PageBuilder;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;

it('uses the custom builder for page queries', function (): void {
    expect(Page::query())->toBeInstanceOf(PageBuilder::class);
});

it('casts the status to the page status enum', function (): void {
    $page = Page::factory()->create();

    expect($page->refresh()->status)->toBe(PageStatus::Draft);
});

it('only returns published pages', function (): void {
    $published = Page::factory()->published()->create();
    Page::factory()->create();

    expect(Page::query()->published()->pluck('id')->all())->toBe([$published->id]);
});

it('only returns draft pages', function (): void {
    Page::factory()->published()->create();
    $draft = Page::factory()->create();

    expect(Page::query()->draft()->pluck('id')->all())->toBe([$draft->id]);
});

it('orders pages by the most recent edit first', function (): void {
    $older = Page::factory()->create(['updated_at' => now()->subDays(2)]);
    $newest = Page::factory()->create(['updated_at' => now()->subMinute()]);
    $middle = Page::factory()->create(['updated_at' => now()->subDay()]);

    expect(Page::query()->recentlyEdited()->pluck('id')->all())->toBe([$newest->id, $middle->id, $older->id]);
});
