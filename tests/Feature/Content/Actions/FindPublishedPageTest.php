<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\FindPublishedPage;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Models\Page;

it('finds the published page at a path', function (): void {
    Page::factory()->published()->at('/over-ons')->create();
    $team = Page::factory()->published()->at('/over-ons/team')->create();

    expect(FindPublishedPage::run(PagePath::fromString('over-ons/team/'))?->is($team))->toBeTrue();
});

it('finds the published home page at the root', function (): void {
    $home = Page::factory()->published()->at('/')->create();

    expect(FindPublishedPage::run(PagePath::fromString(''))?->is($home))->toBeTrue();
});

it('finds a hidden page, because its address still reaches it', function (): void {
    $privacy = Page::factory()->published()->hidden()->at('/privacy')->create();

    expect(FindPublishedPage::run(PagePath::fromString('/privacy'))?->is($privacy))->toBeTrue();
});

it('finds nothing at a path without a page', function (): void {
    Page::factory()->published()->at('/over-ons')->create();

    expect(FindPublishedPage::run(PagePath::fromString('/bestaat-niet')))->toBeNull();
});

it('finds nothing for a draft page', function (): void {
    Page::factory()->at('/concept')->create();

    expect(FindPublishedPage::run(PagePath::fromString('/concept')))->toBeNull();
});

it('finds nothing for a published page without an address', function (): void {
    Page::factory()->published()->create(['slug' => 'over-ons']);

    expect(FindPublishedPage::run(PagePath::fromString('/over-ons')))->toBeNull();
});
