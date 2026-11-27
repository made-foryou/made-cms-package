<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\SchedulePublication;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Events\PublicationScheduled;
use Made\CmsPackage\Content\Exceptions\PublicationMomentPassed;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 14:00:20'));
});

it('plans the publication of a page at a moment, by the editor who plans it', function (): void {
    $editor = Editor::factory()->create();
    $page = Page::factory()->published()->withDraft([storedStrip(id: 'a')])->at('/teams')->create();

    SchedulePublication::run($page, CarbonImmutable::parse('2026-11-28 09:00:00'), $editor->id);

    $page = Page::query()->findOrFail($page->id);

    expect($page->publish_at?->toDateTimeString())->toBe('2026-11-28 09:00:00')
        ->and($page->scheduled_by)->toBe($editor->id)
        ->and($page->updated_by)->toBe($editor->id)
        ->and($page->status)->toBe(PageStatus::Published)
        ->and($page->draft_strips)->toBeStoredAs([storedStrip(id: 'a')]);
});

it('plans a page without a draft too, so the editor may choose the moment first', function (): void {
    $page = Page::factory()->published()->at('/teams')->create();

    SchedulePublication::run($page, CarbonImmutable::parse('2026-11-28 09:00:00'));

    expect($page->refresh()->publish_at?->toDateTimeString())->toBe('2026-11-28 09:00:00')
        ->and($page->draft_strips)->toBeNull();
});

it('plans in whole minutes', function (): void {
    $page = Page::factory()->at('/teams')->create();

    SchedulePublication::run($page, CarbonImmutable::parse('2026-11-28 09:00:45'));

    expect($page->refresh()->publish_at?->toDateTimeString())->toBe('2026-11-28 09:00:00');
});

it('stores the moment in the timezone of the app, whatever the timezone of the moment it gets', function (): void {
    $page = Page::factory()->at('/teams')->create();

    SchedulePublication::run($page, CarbonImmutable::parse('2026-11-28 10:00:00', 'Europe/Amsterdam'));

    expect(DB::table('cms_pages')->where('id', $page->id)->value('publish_at'))->toStartWith('2026-11-28 09:00:00');
});

it('refuses a moment in the past or in the present minute and plans nothing', function (string $moment): void {
    $page = Page::factory()->at('/teams')->create();

    expect(fn (): Page => SchedulePublication::run($page, CarbonImmutable::parse($moment)))->toThrow(PublicationMomentPassed::class)
        ->and($page->refresh()->publish_at)->toBeNull();
})->with(['2026-11-27 13:59:00', '2026-11-27 14:00:00']);

it('plans the next minute', function (): void {
    $page = Page::factory()->at('/teams')->create();

    SchedulePublication::run($page, CarbonImmutable::parse('2026-11-27 14:01:00'));

    expect($page->refresh()->publish_at?->toDateTimeString())->toBe('2026-11-27 14:01:00');
});

it('keeps the moment the page is planned at, also once it passed, and changes nothing', function (): void {
    $planner = Editor::factory()->create();
    $page = Page::factory()->at('/teams')->scheduledAt(CarbonImmutable::parse('2026-11-27 13:00:00'), $planner)->create();
    $updatedAt = $page->refresh()->updated_at;

    SchedulePublication::run($page, CarbonImmutable::parse('2026-11-27 13:00:00'), Editor::factory()->create()->id);

    $page = Page::query()->findOrFail($page->id);

    expect($page->publish_at?->toDateTimeString())->toBe('2026-11-27 13:00:00')
        ->and($page->scheduled_by)->toBe($planner->id)
        ->and($page->updated_at?->toDateTimeString())->toBe($updatedAt?->toDateTimeString());
});

it('plans another moment, by the editor who changes it', function (): void {
    $editor = Editor::factory()->create();
    $page = Page::factory()->at('/teams')->scheduledAt(CarbonImmutable::parse('2026-11-28 09:00:00'), Editor::factory()->create())->create();

    SchedulePublication::run($page, CarbonImmutable::parse('2026-11-29 09:00:00'), $editor->id);

    expect($page->refresh()->publish_at?->toDateTimeString())->toBe('2026-11-29 09:00:00')
        ->and($page->scheduled_by)->toBe($editor->id);
});

it('forgets the planned publication a strip stopped', function (): void {
    $page = Page::factory()->at('/teams')->publicationFailedAt(CarbonImmutable::parse('2026-11-27 09:00:00'))->create();

    SchedulePublication::run($page, CarbonImmutable::parse('2026-11-28 09:00:00'));

    expect($page->refresh()->publish_failed_at)->toBeNull();
});

it('tells that the publication is planned, after the commit', function (): void {
    Event::fake([PublicationScheduled::class]);
    $page = Page::factory()->at('/teams')->create();

    DB::transaction(function () use ($page): void {
        SchedulePublication::run($page, CarbonImmutable::parse('2026-11-28 09:00:00'));

        Event::assertNotDispatched(PublicationScheduled::class);
    });

    Event::assertDispatchedTimes(PublicationScheduled::class, 1);
    Event::assertDispatched(PublicationScheduled::class, fn (PublicationScheduled $event): bool => $event->page->is($page)
        && $event->page->publish_at?->toDateTimeString() === '2026-11-28 09:00:00');
});

it('tells nothing when the moment stays the same', function (): void {
    Event::fake([PublicationScheduled::class]);
    $page = Page::factory()->at('/teams')->scheduledAt(CarbonImmutable::parse('2026-11-28 09:00:00'))->create();

    SchedulePublication::run($page, CarbonImmutable::parse('2026-11-28 09:00:00'));

    Event::assertNotDispatched(PublicationScheduled::class);
});
