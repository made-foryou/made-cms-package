<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Attention\StaleDraftSource;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-21 12:00:00'));
});

/**
 * A published page whose draft began a number of days ago.
 */
function pageWithDraftOf(float $days): Page
{
    return Page::factory()->published()->withStrips([storedStrip(id: 'a')])
        ->withDraft([storedStrip(id: 'b')])
        ->draftSince(CarbonImmutable::now()->subHours((int) round($days * 24)))
        ->create();
}

it('asks for attention once the draft of a published page is open longer than the configured days', function (): void {
    expect((new StaleDraftSource)->items(pageWithDraftOf(6)))->toEqual([
        new AttentionItem(AttentionReason::StaleDraft, AttentionSeverity::High, ['days' => 6]),
    ]);
});

it('counts whole days', function (): void {
    expect((new StaleDraftSource)->items(pageWithDraftOf(7.9)))->toEqual([
        new AttentionItem(AttentionReason::StaleDraft, AttentionSeverity::High, ['days' => 7]),
    ]);
});

it('leaves a draft alone up to the configured days', function (float $days): void {
    expect((new StaleDraftSource)->items(pageWithDraftOf($days)))->toBe([]);
})->with([0.0, 1.0, 5.0, 5.9]);

it('follows the days of the config', function (): void {
    config(['made-cms-package.pages.stale_draft_days' => 1]);

    expect((new StaleDraftSource)->items(pageWithDraftOf(2)))->toHaveCount(1);
});

it('leaves a page alone that visitors never saw or that has no draft', function (Closure $page): void {
    expect((new StaleDraftSource)->items($page()))->toBe([]);
})->with([
    'never published' => [static fn (): Page => Page::factory()->withDraft([storedStrip()])->draftSince(CarbonImmutable::now()->subDays(30))->create()],
    'without a draft' => [static fn (): Page => Page::factory()->published()->withStrips([storedStrip()])->create()],
    'a draft of which the start is not known' => [static fn (): Page => Page::factory()->published()->withDraft([storedStrip()])->create(['draft_started_at' => null])],
    'a draft planned to go live' => [static fn (): Page => Page::factory()->published()->withDraft([storedStrip()])
        ->draftSince(CarbonImmutable::now()->subDays(30))->scheduledAt(CarbonImmutable::now()->addDays(3))->create()],
]);
