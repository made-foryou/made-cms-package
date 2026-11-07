<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Content\Actions\RemoveStrip;
use Made\CmsPackage\Content\Events\StripRemoved;
use Made\CmsPackage\Content\Exceptions\StripNotFound;
use Made\CmsPackage\Content\Models\Page;

it('removes a strip from the draft', function (): void {
    $published = [storedStrip(id: 'a'), storedStrip(id: 'b')];
    $page = Page::factory()->published()->withStrips($published)->create();

    RemoveStrip::run($page, 'a');

    $stored = Page::query()->findOrFail($page->id);

    expect(array_column($stored->draft_strips ?? [], 'id'))->toBe(['b'])
        ->and($stored->strips)->toBe($published);
});

it('returns the strip as it was stored, so it can be put back', function (): void {
    $page = Page::factory()->withDraft([storedStrip(id: 'a'), storedStrip('cards', ['kaarten' => []], visible: false, id: 'b')])->create();

    $removed = RemoveStrip::run($page, 'b');

    expect($removed->toArray())->toBe(['id' => 'b', 'type' => 'cards', 'visible' => false, 'values' => ['kaarten' => []]]);
});

it('keeps an empty draft after removing the last strip of a published page', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip(id: 'a')])->create();

    RemoveStrip::run($page, 'a');

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBe([]);
});

it('has no draft left after removing the last strip of a page that was never published', function (): void {
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();

    RemoveStrip::run($page, 'a');

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBeNull();
});

it('dispatches an event for the removed strip', function (): void {
    Event::fake([StripRemoved::class]);
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();

    RemoveStrip::run($page, 'a');

    Event::assertDispatched(StripRemoved::class, fn (StripRemoved $event): bool => $event->page->is($page)
        && $event->strip->id === 'a');
});

it('refuses a strip that is not on the page', function (): void {
    Event::fake([StripRemoved::class]);
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();

    expect(fn (): mixed => RemoveStrip::run($page, 'x'))->toThrow(StripNotFound::class);

    expect(array_column(Page::query()->findOrFail($page->id)->draft_strips ?? [], 'id'))->toBe(['a']);
    Event::assertNotDispatched(StripRemoved::class);
});

it('keeps strips of a type that no longer exists when another strip is removed', function (): void {
    $old = storedStrip('oud', ['x' => 1], id: 'old');
    $page = Page::factory()->withDraft([storedStrip(id: 'a'), $old])->create();

    RemoveStrip::run($page, 'a');

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBe([$old]);
});
