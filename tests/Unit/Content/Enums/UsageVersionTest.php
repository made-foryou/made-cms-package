<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Enums\UsageVersion;
use Made\CmsPackage\Content\Models\Page;

it('stores the version of a place as a string value', function (): void {
    expect(array_map(static fn (UsageVersion $version): string => $version->value, UsageVersion::cases()))
        ->toBe(['draft', 'published', 'page']);
});

it('takes the draft as the working copy while a page has one', function (): void {
    $page = Page::factory()->published()->withDraft([storedStrip('hero')])->make();

    expect(UsageVersion::workingCopyOf($page))->toBe(UsageVersion::Draft);
});

it('takes the published version as the working copy of a page without a draft', function (): void {
    expect(UsageVersion::workingCopyOf(Page::factory()->published()->make()))->toBe(UsageVersion::Published)
        ->and(UsageVersion::workingCopyOf(Page::factory()->make()))->toBe(UsageVersion::Published);
});
