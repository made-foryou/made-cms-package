<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Attention\PublicationFailedSource;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Page;

it('asks for attention at once when a strip stopped the planned publication of the draft', function (Closure $page): void {
    expect((new PublicationFailedSource)->items($page()))->toEqual([
        new AttentionItem(AttentionReason::PublicationFailed, AttentionSeverity::High),
    ]);
})->with([
    'a published page' => [static fn (): Page => Page::factory()->published()->withDraft([storedStrip(id: 'a')])
        ->publicationFailedAt(CarbonImmutable::parse('2026-11-28 09:00:00'))->create()],
    'a page that was never published' => [static fn (): Page => Page::factory()->withDraft([storedStrip(id: 'a')])
        ->publicationFailedAt(CarbonImmutable::parse('2026-11-28 09:00:00'))->create()],
]);

it('leaves a page alone that no strip stopped, or a live page without a draft to publish', function (Closure $page): void {
    expect((new PublicationFailedSource)->items($page()))->toBe([]);
})->with([
    'nothing stopped' => [static fn (): Page => Page::factory()->published()->withDraft([storedStrip(id: 'a')])->create()],
    'no draft any more' => [static fn (): Page => Page::factory()->published()
        ->publicationFailedAt(CarbonImmutable::parse('2026-11-28 09:00:00'))->create()],
]);
