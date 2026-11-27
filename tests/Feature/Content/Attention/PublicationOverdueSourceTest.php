<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Attention\PublicationOverdueSource;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-28 09:00:00'));
});

it('asks for attention at once when a planned publication is more than five minutes late', function (string $planned): void {
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->scheduledAt(CarbonImmutable::parse($planned))->create();

    expect((new PublicationOverdueSource)->items($page))->toEqual([
        new AttentionItem(AttentionReason::PublicationOverdue, AttentionSeverity::High),
    ]);
})->with(['2026-11-28 08:54:00', '2026-11-27 09:00:00']);

it('gives the scheduler five minutes, and leaves a page planned later or not at all', function (?string $planned): void {
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create(['publish_at' => $planned]);

    expect((new PublicationOverdueSource)->items($page))->toBe([]);
})->with(['2026-11-28 08:55:00', '2026-11-28 08:59:00', '2026-11-28 10:00:00', null]);
