<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Attention\EmptyPageSource;
use Made\CmsPackage\Content\Data\AttentionContext;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Page;

it('asks for content on a page that is empty and was never published', function (): void {
    expect((new EmptyPageSource)->items(Page::factory()->create(), new AttentionContext))->toEqual([
        new AttentionItem(AttentionReason::Empty, AttentionSeverity::Medium),
    ]);
});

it('leaves a page alone that holds a strip or was published', function (Closure $page): void {
    expect((new EmptyPageSource)->items($page(), new AttentionContext))->toBe([]);
})->with([
    'a strip in the draft' => [static fn (): Page => Page::factory()->withDraft([storedStrip()])->create()],
    'published without strips' => [static fn (): Page => Page::factory()->published()->create()],
]);
