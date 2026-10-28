<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Builders\StripBuilder;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Strip;

it('uses the custom builder for strip queries', function (): void {
    expect(Strip::query())->toBeInstanceOf(StripBuilder::class);
});

it('gives a page its strips in the order they show', function (): void {
    $page = Page::factory()->create();
    $second = Strip::factory()->for($page)->create(['position' => 1]);
    $first = Strip::factory()->for($page)->create(['position' => 0]);
    Strip::factory()->create(['position' => 0]);

    expect($page->strips->modelKeys())->toBe([$first->id, $second->id]);
});

it('serialises a strip to its id, type and content only', function (): void {
    $strip = Strip::factory()->create(['type' => 'hero', 'content' => ['title' => 'TTV Merwestad']]);

    expect($strip->refresh()->toArray())->toBe([
        'id' => $strip->id,
        'type' => 'hero',
        'content' => ['title' => 'TTV Merwestad'],
    ]);
});

it('removes the strips together with their page', function (): void {
    $strip = Strip::factory()->create();

    $strip->page->delete();

    expect(Strip::query()->count())->toBe(0);
});
