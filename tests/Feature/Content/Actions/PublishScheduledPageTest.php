<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\PublishScheduledPage;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Enums\ScheduledPublicationOutcome;
use Made\CmsPackage\Content\Events\PagePublished;
use Made\CmsPackage\Content\Events\ScheduledPublicationFailed;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\PageVersion;

beforeEach(function (): void {
    useStripTypes();
    $this->moment = CarbonImmutable::parse('2026-11-28 09:00:00');
    $this->travelTo($this->moment->addSeconds(12));
});

/**
 * @return array<string, mixed>
 */
function scheduledHero(string $heading = 'Nieuwe zaal'): array
{
    return ['kop' => $heading, 'knoppen' => [['tekst' => 'Lees meer', 'gaat_naar' => ['url' => '/nieuws']]]];
}

it('puts the draft live at the planned moment, by the editor who planned it', function (): void {
    $planner = Editor::factory()->create();
    $draft = [storedStrip('hero', scheduledHero(), id: 'hero')];
    $page = Page::factory()->published()->withStrips([storedStrip('hero', scheduledHero('Oud'), id: 'hero')])->withDraft($draft)
        ->scheduledAt($this->moment, $planner)->create();

    $outcome = PublishScheduledPage::run($page, $this->moment);

    $stored = Page::query()->findOrFail($page->id);
    $version = PageVersion::query()->ofPage($page)->newestFirst()->firstOrFail();

    expect($outcome)->toBe(ScheduledPublicationOutcome::Published)
        ->and($stored->strips)->toBeStoredAs($draft)
        ->and($stored->draft_strips)->toBeNull()
        ->and($stored->published_at?->equalTo(now()))->toBeTrue()
        ->and($stored->publish_at)->toBeNull()
        ->and($stored->scheduled_by)->toBeNull()
        ->and($stored->updated_by)->toBe($planner->id)
        ->and($version->strips)->toBeStoredAs($draft)
        ->and($version->published_by)->toBe($planner->id);
});

it('puts a page live that was never published', function (): void {
    Event::fake([PagePublished::class]);
    $page = Page::factory()->withDraft([storedStrip('hero', scheduledHero(), id: 'hero')])->scheduledAt($this->moment)->create();

    expect(PublishScheduledPage::run($page, $this->moment))->toBe(ScheduledPublicationOutcome::Published)
        ->and($page->refresh()->status)->toBe(PageStatus::Published);
    Event::assertDispatched(PagePublished::class, fn (PagePublished $event): bool => $event->page->is($page));
});

it('leaves a page whose plan moved or was taken away in the meantime', function (?string $planned): void {
    $draft = [storedStrip('hero', scheduledHero(), id: 'hero')];
    $page = Page::factory()->withDraft($draft)->create(['publish_at' => $planned]);

    expect(PublishScheduledPage::run($page, $this->moment))->toBe(ScheduledPublicationOutcome::Moved)
        ->and($page->refresh()->status)->toBe(PageStatus::Draft)
        ->and($page->draft_strips)->toBeStoredAs($draft)
        ->and($page->publish_at?->toDateTimeString())->toBe($planned)
        ->and(PageVersion::query()->count())->toBe(0);
})->with([
    'moved' => '2026-11-29 09:00:00',
    'taken away' => null,
]);

it('only drops the plan of a live page without a draft, which has nothing to publish', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip('hero', scheduledHero(), id: 'hero')])->scheduledAt($this->moment)->create();

    expect(PublishScheduledPage::run($page, $this->moment))->toBe(ScheduledPublicationOutcome::NothingToPublish)
        ->and($page->refresh()->publish_at)->toBeNull()
        ->and($page->publish_failed_at)->toBeNull()
        ->and(PageVersion::query()->count())->toBe(0);
});

it('stops the plan and keeps the draft when a strip stops the publication', function (): void {
    Event::fake([PagePublished::class, ScheduledPublicationFailed::class]);
    $live = [storedStrip('hero', scheduledHero('Oud'), id: 'hero')];
    $draft = [storedStrip('hero', ['kop' => null], id: 'hero')];
    $page = Page::factory()->published()->withStrips($live)->withDraft($draft)->scheduledAt($this->moment, Editor::factory()->create())->create();

    DB::transaction(function () use ($page): void {
        expect(PublishScheduledPage::run($page, $this->moment))->toBe(ScheduledPublicationOutcome::Refused);

        Event::assertNotDispatched(ScheduledPublicationFailed::class);
    });

    $stored = Page::query()->findOrFail($page->id);

    expect($stored->strips)->toBeStoredAs($live)
        ->and($stored->draft_strips)->toBeStoredAs($draft)
        ->and($stored->publish_at)->toBeNull()
        ->and($stored->scheduled_by)->toBeNull()
        ->and($stored->publish_failed_at?->toDateTimeString())->toBe('2026-11-28 09:00:00')
        ->and(PageVersion::query()->count())->toBe(0);
    Event::assertNotDispatched(PagePublished::class);
    Event::assertDispatchedTimes(ScheduledPublicationFailed::class, 1);
    Event::assertDispatched(ScheduledPublicationFailed::class, fn (ScheduledPublicationFailed $event): bool => $event->page->is($page));
});
