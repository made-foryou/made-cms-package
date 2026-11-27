<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Actions\PublishScheduledPages;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
    $this->travelTo(CarbonImmutable::parse('2026-11-28 09:00:30'));
});

/**
 * @return list<array<string, mixed>>
 */
function scheduledDraft(?string $heading = 'Nieuwe zaal'): array
{
    return [storedStrip('hero', ['kop' => $heading, 'knoppen' => [['tekst' => 'Lees meer', 'gaat_naar' => ['url' => '/nieuws']]]], id: 'hero')];
}

it('publishes every page whose moment came and leaves the pages planned later', function (): void {
    $due = Page::factory()->withDraft(scheduledDraft())->scheduledAt(CarbonImmutable::parse('2026-11-28 09:00:00'))->create();
    $late = Page::factory()->withDraft(scheduledDraft())->scheduledAt(CarbonImmutable::parse('2026-11-28 08:15:00'))->create();
    $later = Page::factory()->withDraft(scheduledDraft())->scheduledAt(CarbonImmutable::parse('2026-11-28 09:01:00'))->create();
    $unplanned = Page::factory()->withDraft(scheduledDraft())->create();

    $result = PublishScheduledPages::run();

    expect($result->published)->toBe(2)
        ->and($result->refused)->toBe(0)
        ->and($due->refresh()->status)->toBe(PageStatus::Published)
        ->and($late->refresh()->status)->toBe(PageStatus::Published)
        ->and($later->refresh()->status)->toBe(PageStatus::Draft)
        ->and($later->publish_at)->not->toBeNull()
        ->and($unplanned->refresh()->status)->toBe(PageStatus::Draft);
});

it('publishes the others when a strip stops one of them', function (): void {
    $broken = Page::factory()->withDraft(scheduledDraft(null))->scheduledAt(CarbonImmutable::parse('2026-11-28 08:00:00'))->create();
    $fine = Page::factory()->withDraft(scheduledDraft())->scheduledAt(CarbonImmutable::parse('2026-11-28 09:00:00'))->create();

    $result = PublishScheduledPages::run();

    expect($result->published)->toBe(1)
        ->and($result->refused)->toBe(1)
        ->and($broken->refresh()->status)->toBe(PageStatus::Draft)
        ->and($broken->publish_failed_at?->toDateTimeString())->toBe('2026-11-28 08:00:00')
        ->and($fine->refresh()->status)->toBe(PageStatus::Published);
});

it('counts a live page without a draft as neither', function (): void {
    $page = Page::factory()->published()->scheduledAt(CarbonImmutable::parse('2026-11-28 09:00:00'))->create();

    $result = PublishScheduledPages::run();

    expect($result->published)->toBe(0)
        ->and($result->refused)->toBe(0)
        ->and($page->refresh()->publish_at)->toBeNull();
});

it('leaves a deleted page', function (): void {
    $page = Page::factory()->withDraft(scheduledDraft())->scheduledAt(CarbonImmutable::parse('2026-11-28 09:00:00'))
        ->deletedAt(now()->subHour())->create();

    expect(PublishScheduledPages::run()->published)->toBe(0)
        ->and(Page::query()->withTrashed()->findOrFail($page->id)->status)->toBe(PageStatus::Draft);
});

it('publishes what came at another moment it is given', function (): void {
    $page = Page::factory()->withDraft(scheduledDraft())->scheduledAt(CarbonImmutable::parse('2026-11-28 10:00:00'))->create();

    expect(PublishScheduledPages::run(CarbonImmutable::parse('2026-11-28 10:00:00'))->published)->toBe(1)
        ->and($page->refresh()->status)->toBe(PageStatus::Published);
});
