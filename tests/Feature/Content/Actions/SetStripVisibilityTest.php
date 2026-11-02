<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Content\Actions\SetStripVisibility;
use Made\CmsPackage\Content\Events\StripVisibilityChanged;
use Made\CmsPackage\Content\Exceptions\StripNotFound;
use Made\CmsPackage\Content\Models\Page;

it('hides and shows a strip in the draft', function (): void {
    $published = [storedStrip(id: 'a')];
    $page = Page::factory()->published()->withStrips($published)->create();

    $hidden = SetStripVisibility::run($page, 'a', false);

    $stored = Page::query()->findOrFail($page->id);

    expect($hidden->visible)->toBeFalse()
        ->and($stored->draft_strips[0]['visible'] ?? null)->toBeFalse()
        ->and($stored->strips)->toBe($published);

    $shown = SetStripVisibility::run($stored, 'a', true);

    expect($shown->visible)->toBeTrue()
        ->and(Page::query()->findOrFail($page->id)->draft_strips[0]['visible'] ?? null)->toBeTrue();
});

it('dispatches an event for the strip', function (): void {
    Event::fake([StripVisibilityChanged::class]);
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();

    SetStripVisibility::run($page, 'a', false);

    Event::assertDispatched(StripVisibilityChanged::class, fn (StripVisibilityChanged $event): bool => $event->page->is($page)
        && $event->strip->id === 'a'
        && $event->strip->visible === false);
});

it('refuses a strip that is not on the page', function (): void {
    Event::fake([StripVisibilityChanged::class]);
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();

    expect(fn (): mixed => SetStripVisibility::run($page, 'x', false))->toThrow(StripNotFound::class);

    Event::assertNotDispatched(StripVisibilityChanged::class);
});

it('hides a strip of a type that no longer exists without touching its values', function (): void {
    $page = Page::factory()->withDraft([storedStrip('oud', ['x' => 1], id: 'old')])->create();

    SetStripVisibility::run($page, 'old', false);

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBe([storedStrip('oud', ['x' => 1], visible: false, id: 'old')]);
});
