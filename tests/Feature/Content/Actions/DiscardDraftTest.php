<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Content\Actions\DiscardDraft;
use Made\CmsPackage\Content\Events\DraftDiscarded;
use Made\CmsPackage\Content\Models\Page;

it('discards the draft and keeps the published strips', function (): void {
    $published = [storedStrip(id: 'a'), storedStrip('oud', ['x' => 1], id: 'old')];
    $page = Page::factory()->published()->withStrips($published)->withDraft([storedStrip(id: 'b')])->create();

    $discarded = DiscardDraft::run($page);

    $stored = Page::query()->findOrFail($page->id);

    expect($discarded->is($page))->toBeTrue()
        ->and($stored->draft_strips)->toBeNull()
        ->and($stored->strips)->toBe($published);
});

it('dispatches an event for the page', function (): void {
    Event::fake([DraftDiscarded::class]);
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();

    DiscardDraft::run($page);

    Event::assertDispatched(DraftDiscarded::class, fn (DraftDiscarded $event): bool => $event->page->is($page));
});

it('does nothing without a draft', function (): void {
    Event::fake([DraftDiscarded::class]);
    $page = Page::factory()->withStrips([storedStrip(id: 'a')])->create(['updated_at' => now()->subDay()]);

    DiscardDraft::run($page);

    $stored = Page::query()->findOrFail($page->id);

    expect($stored->updated_at?->lessThan(now()->subHour()))->toBeTrue();
    Event::assertNotDispatched(DraftDiscarded::class);
});
