<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\PublishPage;
use Made\CmsPackage\Content\Actions\RestorePageVersion;
use Made\CmsPackage\Content\Enums\UsageVersion;
use Made\CmsPackage\Content\Events\PageVersionRestored;
use Made\CmsPackage\Content\Exceptions\PageVersionNotFound;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\PageVersion;

beforeEach(function (): void {
    useStripTypes();
    $this->live = [storedStrip('hero', ['kop' => 'Nu live'], id: 'hero'), storedStrip('cards', id: 'cards')];
    $this->older = [storedStrip('hero', ['kop' => 'Vroeger'], id: 'hero')];
    $this->page = Page::factory()->published()->withStrips($this->live)->create();
    $this->version = PageVersion::factory()->for($this->page)->number(1)->withStrips($this->older)->create();
    PageVersion::factory()->for($this->page)->number(2)->withStrips($this->live)->create();
});

it('puts the strips of a version back as the draft', function (): void {
    $restored = RestorePageVersion::run($this->page, $this->version);

    $stored = Page::query()->findOrFail($this->page->id);

    expect($stored->draft_strips)->toBeStoredAs($this->older)
        ->and($stored->strips)->toBeStoredAs($this->live)
        ->and($stored->draft_started_at)->not->toBeNull()
        ->and($restored->number)->toBe(1)
        ->and($restored->replacedDraft)->toBeFalse()
        ->and($restored->draft)->toBeTrue();
});

it('replaces the draft that was there', function (): void {
    $this->page->update(['draft_strips' => [storedStrip('cards', id: 'nieuw')]]);

    $restored = RestorePageVersion::run($this->page, $this->version);

    expect(Page::query()->findOrFail($this->page->id)->draft_strips)->toBeStoredAs($this->older)
        ->and($restored->replacedDraft)->toBeTrue();
});

it('leaves no draft when the version is what visitors see', function (): void {
    $this->page->update(['draft_strips' => [storedStrip('cards', id: 'nieuw')]]);
    $same = PageVersion::factory()->for($this->page)->number(3)->withStrips($this->live)->create();

    $restored = RestorePageVersion::run($this->page, $same);

    expect(Page::query()->findOrFail($this->page->id)->draft_strips)->toBeNull()
        ->and($restored->replacedDraft)->toBeTrue()
        ->and($restored->draft)->toBeFalse();
});

it('notes the editor who put the version back', function (): void {
    $editor = Editor::factory()->create();

    RestorePageVersion::run($this->page, $this->version, $editor->id);

    expect(Page::query()->findOrFail($this->page->id)->updated_by)->toBe($editor->id);
});

it('gives the files of the restored draft their places', function (): void {
    $photo = Media::factory()->create();
    $version = PageVersion::factory()->for($this->page)->number(3)
        ->withStrips([storedStrip('hero', ['kop' => 'Met foto', 'foto' => $photo->id], id: 'hero')])
        ->create();

    RestorePageVersion::run($this->page, $version);

    expect(MediaUsage::query()->onPage($this->page)->where('version', UsageVersion::Draft)->pluck('media_id')->all())
        ->toBe([$photo->id]);
});

it('brings strips back as they were stored, also of a type that left the catalogue and a file that is gone', function (): void {
    $strips = [
        storedStrip('quote', ['tekst' => 'Uit de catalogus'], id: 'quote'),
        storedStrip('hero', ['kop' => 'Foto weg', 'foto' => missingId()], id: 'hero'),
    ];
    $version = PageVersion::factory()->for($this->page)->number(3)->withStrips($strips)->create();

    RestorePageVersion::run($this->page, $version);

    expect(Page::query()->findOrFail($this->page->id)->draft_strips)->toBeStoredAs($strips)
        ->and(MediaUsage::query()->onPage($this->page)->where('version', UsageVersion::Draft)->count())->toBe(0);
});

it('refuses a version of another page', function (): void {
    $other = PageVersion::factory()->for(Page::factory())->withStrips($this->older)->create();

    expect(fn (): mixed => RestorePageVersion::run($this->page, $other))->toThrow(PageVersionNotFound::class)
        ->and(Page::query()->findOrFail($this->page->id)->draft_strips)->toBeNull();
});

it('dispatches an event for the page and the version', function (): void {
    Event::fake([PageVersionRestored::class]);

    RestorePageVersion::run($this->page, $this->version);

    Event::assertDispatched(
        PageVersionRestored::class,
        fn (PageVersionRestored $event): bool => $event->page->is($this->page) && $event->version->is($this->version),
    );
});

it('publishes the restored version as the next version', function (): void {
    $cards = [storedStrip('cards', ['kaarten' => []], id: 'cards')];
    $version = PageVersion::factory()->for($this->page)->number(3)->withStrips($cards)->create();

    RestorePageVersion::run($this->page, $version);
    PublishPage::run($this->page);

    $newest = PageVersion::query()->ofPage($this->page)->newestFirst()->firstOrFail();

    expect($newest->number)->toBe(4)
        ->and($newest->strips)->toBeStoredAs($cards)
        ->and(Page::query()->findOrFail($this->page->id)->strips)->toBeStoredAs($cards);
});
