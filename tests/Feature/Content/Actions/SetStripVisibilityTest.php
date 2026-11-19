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
        ->and($stored->strips)->toBeStoredAs($published);

    $shown = SetStripVisibility::run($stored, 'a', true);

    $stored = Page::query()->findOrFail($page->id);

    expect($shown->visible)->toBeTrue()
        ->and(($stored->draft_strips ?? $stored->strips)[0]['visible'] ?? null)->toBeTrue();
});

it('drops the draft once a hidden strip is shown again', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip(id: 'a')])->create();

    SetStripVisibility::run($page, 'a', false);

    expect($page->draft_strips)->not->toBeNull();

    SetStripVisibility::run($page, 'a', true);

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBeNull();
});

it('keeps a draft that still changes something else', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip(id: 'a'), storedStrip(id: 'b')])->create();

    SetStripVisibility::run($page, 'a', false);
    SetStripVisibility::run($page, 'b', false);
    SetStripVisibility::run($page, 'a', true);

    expect(Page::query()->findOrFail($page->id)->draft_strips)->not->toBeNull();
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

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBeStoredAs([storedStrip('oud', ['x' => 1], visible: false, id: 'old')]);
});
