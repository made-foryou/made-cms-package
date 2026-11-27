<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Attention\MetaTitleSharedSource;
use Made\CmsPackage\Content\Data\AttentionContext;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://example.test', 'app.name' => 'TTV Merwestad']);
});

it('names the other pages with the same title in Google', function (): void {
    $page = Page::factory()->published()->at('/contact')->withMeta('Contact')->create();

    expect((new MetaTitleSharedSource)->items($page, new AttentionContext([$page->id => ['Lid worden', 'Over ons']])))->toEqual([
        new AttentionItem(AttentionReason::MetaTitleShared, AttentionSeverity::Medium, ['count' => 2], ['Lid worden', 'Over ons']),
    ]);
});

it('leaves a page alone whose title is its own', function (): void {
    $page = Page::factory()->published()->at('/contact')->withMeta('Contact')->create();

    expect((new MetaTitleSharedSource)->items($page, new AttentionContext([$page->id + 1 => ['Contact']])))->toBe([]);
});

it('leaves a page alone that Google does not show', function (): void {
    $page = Page::factory()->published()->noindex()->at('/contact')->withMeta('Contact')->create();

    expect((new MetaTitleSharedSource)->items($page, new AttentionContext([$page->id => ['Lid worden']])))->toBe([]);
});
