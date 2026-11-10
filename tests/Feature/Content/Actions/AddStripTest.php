<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Content\Actions\AddStrip;
use Made\CmsPackage\Content\Events\StripAdded;
use Made\CmsPackage\Content\Exceptions\InvalidStripPosition;
use Made\CmsPackage\Content\Exceptions\UnknownStripType;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\Strips\InlineStripType;

beforeEach(function (): void {
    useStripTypes();
});

it('adds a strip of a type to the draft with the defaults of the type', function (): void {
    $page = Page::factory()->create();

    $strip = AddStrip::run($page, 'hero', 0);

    $stored = Page::query()->findOrFail($page->id);

    expect($strip->type)->toBe('hero')
        ->and($strip->visible)->toBeTrue()
        ->and($strip->id)->toBeUuid()
        ->and($strip->values)->toBe([
            'label' => null,
            'kop' => 'Welkom',
            'intro' => null,
            'tekst' => null,
            'actie' => null,
            'knoppen' => [],
            'foto' => null,
        ])
        ->and($stored->draft_strips)->toBe([$strip->toArray()])
        ->and($stored->strips)->toBe([]);
});

it('gives the items of a list in the defaults an id', function (): void {
    app()->instance(StripTypes::class, new StripTypes([
        new InlineStripType('uren', [Items::make('regels', [Text::make('dag')])->default([['dag' => 'Maandag'], ['dag' => 'Dinsdag']])]),
    ]));

    $strip = AddStrip::run(Page::factory()->create(), 'uren', 0);

    /** @var list<array<string, mixed>> $regels */
    $regels = $strip->values['regels'];

    expect(array_column($regels, 'dag'))->toBe(['Maandag', 'Dinsdag'])
        ->and($regels[0]['id'] ?? null)->toBeUuid()
        ->and($regels[1]['id'] ?? null)->toBeUuid()
        ->and($regels[0]['id'] ?? null)->not->toBe($regels[1]['id'] ?? null);
});

it('starts the draft as a copy of the published strips', function (): void {
    $published = [storedStrip(id: 'a'), storedStrip(id: 'b')];
    $page = Page::factory()->published()->withStrips($published)->create();

    $strip = AddStrip::run($page, 'cards', 1);

    $stored = Page::query()->findOrFail($page->id);

    expect(array_column($stored->draft_strips ?? [], 'id'))->toBe(['a', $strip->id, 'b'])
        ->and($stored->strips)->toBe($published);
});

it('adds to an existing draft', function (): void {
    $page = Page::factory()->withStrips([storedStrip(id: 'live')])->withDraft([storedStrip(id: 'a')])->create();

    $strip = AddStrip::run($page, 'cards', 1);

    expect(array_column(Page::query()->findOrFail($page->id)->draft_strips ?? [], 'id'))->toBe(['a', $strip->id]);
});

it('dispatches an event for the added strip', function (): void {
    Event::fake([StripAdded::class]);
    $page = Page::factory()->create();

    $strip = AddStrip::run($page, 'cards', 0);

    Event::assertDispatched(StripAdded::class, fn (StripAdded $event): bool => $event->page->is($page)
        && $event->strip->id === $strip->id
        && $event->position === 0);
});

it('refuses a position outside the page', function (int $position): void {
    Event::fake([StripAdded::class]);
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();

    expect(fn (): mixed => AddStrip::run($page, 'cards', $position))->toThrow(InvalidStripPosition::class);

    expect(array_column(Page::query()->findOrFail($page->id)->draft_strips ?? [], 'id'))->toBe(['a']);
    Event::assertNotDispatched(StripAdded::class);
})->with([-1, 2]);

it('refuses a type that is not in the catalogue', function (): void {
    Event::fake([StripAdded::class]);
    $page = Page::factory()->create();

    expect(fn (): mixed => AddStrip::run($page, 'onbekend', 0))->toThrow(UnknownStripType::class);

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBeNull();
    Event::assertNotDispatched(StripAdded::class);
});

it('keeps strips of a type that no longer exists', function (): void {
    $old = storedStrip('oud', ['x' => 1], id: 'old');
    $page = Page::factory()->withDraft([$old])->create();

    AddStrip::run($page, 'cards', 1);

    expect(Page::query()->findOrFail($page->id)->draft_strips[0] ?? null)->toBe($old);
});
