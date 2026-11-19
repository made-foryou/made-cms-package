<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Content\Actions\MoveStrip;
use Made\CmsPackage\Content\Events\StripMoved;
use Made\CmsPackage\Content\Exceptions\InvalidStripPosition;
use Made\CmsPackage\Content\Exceptions\StripNotFound;
use Made\CmsPackage\Content\Models\Page;

it('moves a strip in the draft', function (): void {
    $published = [storedStrip(id: 'a'), storedStrip(id: 'b'), storedStrip(id: 'c')];
    $page = Page::factory()->published()->withStrips($published)->create();

    MoveStrip::run($page, 'a', 2);

    $stored = Page::query()->findOrFail($page->id);

    expect(array_column($stored->draft_strips ?? [], 'id'))->toBe(['b', 'c', 'a'])
        ->and($stored->strips)->toBeStoredAs($published);
});

it('dispatches an event with the old and new position', function (): void {
    Event::fake([StripMoved::class]);
    $page = Page::factory()->withDraft([storedStrip(id: 'a'), storedStrip(id: 'b')])->create();

    MoveStrip::run($page, 'b', 0);

    Event::assertDispatched(StripMoved::class, fn (StripMoved $event): bool => $event->page->is($page)
        && $event->strip->id === 'b'
        && $event->from === 1
        && $event->to === 0);
});

it('does nothing when the strip already is at the position', function (): void {
    Event::fake([StripMoved::class]);
    $page = Page::factory()->withStrips([storedStrip(id: 'a'), storedStrip(id: 'b')])->create();

    MoveStrip::run($page, 'b', 1);

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBeNull();
    Event::assertNotDispatched(StripMoved::class);
});

it('refuses a position outside the page', function (int $position): void {
    Event::fake([StripMoved::class]);
    $page = Page::factory()->withDraft([storedStrip(id: 'a'), storedStrip(id: 'b')])->create();

    expect(fn (): mixed => MoveStrip::run($page, 'a', $position))->toThrow(InvalidStripPosition::class);

    expect(array_column(Page::query()->findOrFail($page->id)->draft_strips ?? [], 'id'))->toBe(['a', 'b']);
    Event::assertNotDispatched(StripMoved::class);
})->with([-1, 2]);

it('refuses a strip that is not on the page', function (): void {
    Event::fake([StripMoved::class]);
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();

    expect(fn (): mixed => MoveStrip::run($page, 'x', 0))->toThrow(StripNotFound::class);

    Event::assertNotDispatched(StripMoved::class);
});

it('moves a strip of a type that no longer exists', function (): void {
    $old = storedStrip('oud', ['x' => 1], id: 'old');
    $page = Page::factory()->withDraft([storedStrip(id: 'a'), $old])->create();

    MoveStrip::run($page, 'old', 0);

    expect(Page::query()->findOrFail($page->id)->draft_strips[0] ?? null)->toBeStoredAs($old);
});
