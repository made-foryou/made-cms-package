<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\FindPage;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Models\Page;

it('finds the page at a path whatever its status', function (): void {
    $draft = Page::factory()->at('/concept')->create();

    expect(FindPage::run(PagePath::fromString('Concept/'))?->is($draft))->toBeTrue();
});

it('finds the published page at a path', function (): void {
    $home = Page::factory()->published()->at('/')->create();

    expect(FindPage::run(PagePath::fromString(''))?->is($home))->toBeTrue();
});

it('finds nothing at a path without a page', function (): void {
    Page::factory()->at('/concept')->create();

    expect(FindPage::run(PagePath::fromString('/bestaat-niet')))->toBeNull();
});

it('finds nothing for a page without an address', function (): void {
    Page::factory()->create(['slug' => 'concept']);

    expect(FindPage::run(PagePath::fromString('/concept')))->toBeNull();
});
