<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\CancelScheduledPublication;
use Made\CmsPackage\Content\Events\ScheduledPublicationCancelled;
use Made\CmsPackage\Content\Models\Page;

it('takes the planned publication away and keeps the draft', function (): void {
    $editor = Editor::factory()->create();
    $page = Page::factory()->published()->withDraft([storedStrip(id: 'a')])->at('/teams')
        ->scheduledAt(now()->addDay(), Editor::factory()->create())->create();

    CancelScheduledPublication::run($page, $editor->id);

    $page = Page::query()->findOrFail($page->id);

    expect($page->publish_at)->toBeNull()
        ->and($page->scheduled_by)->toBeNull()
        ->and($page->updated_by)->toBe($editor->id)
        ->and($page->draft_strips)->toBeStoredAs([storedStrip(id: 'a')]);
});

it('keeps the note of a planned publication a strip stopped', function (): void {
    $page = Page::factory()->at('/teams')->publicationFailedAt(CarbonImmutable::parse('2026-11-20 09:00:00'))->create();

    CancelScheduledPublication::run($page);

    expect($page->refresh()->publish_failed_at?->toDateTimeString())->toBe('2026-11-20 09:00:00');
});

it('tells that the plan is gone, after the commit', function (): void {
    Event::fake([ScheduledPublicationCancelled::class]);
    $page = Page::factory()->at('/teams')->scheduledAt(now()->addDay())->create();

    DB::transaction(function () use ($page): void {
        CancelScheduledPublication::run($page);

        Event::assertNotDispatched(ScheduledPublicationCancelled::class);
    });

    Event::assertDispatchedTimes(ScheduledPublicationCancelled::class, 1);
    Event::assertDispatched(ScheduledPublicationCancelled::class, fn (ScheduledPublicationCancelled $event): bool => $event->page->is($page));
});

it('does nothing for a page without a plan', function (): void {
    Event::fake([ScheduledPublicationCancelled::class]);
    $page = Page::factory()->at('/teams')->create();
    $updatedAt = $page->refresh()->updated_at;

    $this->travel(5)->minutes();

    CancelScheduledPublication::run($page, Editor::factory()->create()->id);

    expect($page->refresh()->updated_at?->toDateTimeString())->toBe($updatedAt?->toDateTimeString())
        ->and($page->updated_by)->toBeNull();
    Event::assertNotDispatched(ScheduledPublicationCancelled::class);
});
