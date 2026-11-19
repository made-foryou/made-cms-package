<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Content\Actions\CompareDraft;
use Made\CmsPackage\Content\Actions\RemoveStrip;
use Made\CmsPackage\Content\Actions\RestoreStrip;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Events\StripAdded;
use Made\CmsPackage\Content\Exceptions\InvalidStripPosition;
use Made\CmsPackage\Content\Exceptions\StripAlreadyOnPage;
use Made\CmsPackage\Content\Exceptions\UnknownStripType;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\Strips\Banner;

beforeEach(function (): void {
    useStripTypes();
});

const RESTORED_ITEM = '6f1c2d3e-4a5b-4c6d-8e7f-9a0b1c2d3e4f';

/**
 * A hero strip as the server stored it before it was removed, with the id of its button.
 */
function removedHero(): Strip
{
    return new Strip('hero-1', 'hero', false, [
        'label' => null,
        'kop' => 'TTV Merwestad',
        'intro' => null,
        'tekst' => null,
        'actie' => null,
        'knoppen' => [['id' => RESTORED_ITEM, 'tekst' => 'Lid worden', 'gaat_naar' => ['url' => '/lid-worden'], 'stijl' => 'primary']],
        'foto' => null,
    ]);
}

it('puts a removed strip back in its place with its id, values and visibility', function (): void {
    $page = Page::factory()->withDraft([storedStrip(id: 'a'), storedStrip(id: 'b')])->create();

    $strip = RestoreStrip::run($page, removedHero(), 1, chooseLevels: true);

    expect($strip->toArray())->toBe(removedHero()->toArray())
        ->and(Page::query()->findOrFail($page->id)->draft_strips)->toBeStoredAs([
            storedStrip(id: 'a'),
            removedHero()->toArray(),
            storedStrip(id: 'b'),
        ]);
});

it('leaves nothing to publish after removing a published strip and putting it back', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('cards', id: 'a'),
        removedHero()->toArray(),
        storedStrip('cards', id: 'b'),
    ])->create();

    $removed = RemoveStrip::run($page, 'hero-1');
    RestoreStrip::run($page, $removed, 1, chooseLevels: true);

    expect(CompareDraft::run($page->refresh())->count())->toBe(0);
});

it('drops the draft once a removed strip is put back', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip('cards', id: 'a'), storedStrip('cards', id: 'b')])->create();

    $removed = RemoveStrip::run($page, 'b');

    expect($page->draft_strips)->not->toBeNull();

    RestoreStrip::run($page, $removed, 1, chooseLevels: true);

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBeNull();
});

it('checks the values against the strip type and drops what the type does not know', function (): void {
    $page = Page::factory()->withDraft([])->create();

    $strip = RestoreStrip::run($page, new Strip('hero-1', 'hero', true, ['kop' => 'Welkom', 'onbekend' => 'weg', 'knoppen' => [['tekst' => 'Lid worden', 'gaat_naar' => ['url' => '/lid']]]]), 0, chooseLevels: true);

    expect($strip->values)->not->toHaveKey('onbekend')
        ->and($strip->values['kop'])->toBe('Welkom');

    expect(fn (): mixed => RestoreStrip::run($page, new Strip('hero-2', 'hero', true, ['kop' => null]), 0, chooseLevels: true))
        ->toThrow(ValidationException::class);
});

it('dispatches the event of a strip that comes onto the page', function (): void {
    Event::fake([StripAdded::class]);
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();

    RestoreStrip::run($page, removedHero(), 0, chooseLevels: true);

    Event::assertDispatched(StripAdded::class, fn (StripAdded $event): bool => $event->page->is($page)
        && $event->strip->id === 'hero-1'
        && $event->position === 0);
});

it('refuses a strip that is still on the page', function (): void {
    $page = Page::factory()->withDraft([removedHero()->toArray()])->create();

    expect(fn (): mixed => RestoreStrip::run($page, removedHero(), 0, chooseLevels: true))->toThrow(StripAlreadyOnPage::class);
});

it('refuses a position outside the page', function (int $position): void {
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();

    expect(fn (): mixed => RestoreStrip::run($page, removedHero(), $position, chooseLevels: true))->toThrow(InvalidStripPosition::class);
})->with([-1, 2]);

it('puts back the published copy of a strip whose type left the catalogue, whatever values come along', function (): void {
    $old = storedStrip('oud', ['x' => 'Sponsoren'], id: 'old');
    $page = Page::factory()->published()->withStrips([$old, storedStrip(id: 'a')])->withDraft([$old, storedStrip(id: 'a'), storedStrip(id: 'new')])->create();

    RemoveStrip::run($page, 'old');
    $strip = RestoreStrip::run($page, new Strip('old', 'oud', true, ['x' => 'iets anders']), 0, chooseLevels: true);

    expect($strip->toArray())->toBe($old)
        ->and(Page::query()->findOrFail($page->id)->draft_strips)->toBeStoredAs([$old, storedStrip(id: 'a'), storedStrip(id: 'new')]);
});

it('refuses a strip of an unknown type that the published page does not hold', function (): void {
    $page = Page::factory()->withDraft([])->create();

    expect(fn (): mixed => RestoreStrip::run($page, new Strip('old', 'oud', true, ['x' => 1]), 0, chooseLevels: true))->toThrow(UnknownStripType::class);
});

it('puts a heading that was stored as text back as a text with its level', function (): void {
    app()->instance(StripTypes::class, new StripTypes([new Banner]));
    $page = Page::factory()->withDraft([])->create();

    $strip = RestoreStrip::run($page, new Strip('banner-1', 'banner', true, ['kop' => 'Welkom', 'kaarten' => [['titel' => ['text' => 'Teams', 'tag' => 'h6']]]]), 0, chooseLevels: true);

    expect($strip->values['kop'])->toBe(['text' => 'Welkom', 'tag' => 'h2'])
        ->and($strip->values['kaarten'][0]['titel'])->toBe(['text' => 'Teams', 'tag' => 'h3']);
});

/**
 * A banner as the opbouw sends it back after removing it, with other levels than the published page holds.
 */
function restoredBanner(): Strip
{
    return new Strip('banner-1', 'banner', true, [
        'kop' => ['text' => 'Welkom', 'tag' => 'h1'],
        'kaarten' => [
            ['id' => RESTORED_ITEM, 'titel' => ['text' => 'Teams', 'tag' => 'h3']],
            ['id' => '7a2d3e4f-5b6c-4d7e-9f8a-0b1c2d3e4f5a', 'titel' => ['text' => 'Nieuw', 'tag' => 'h2']],
        ],
    ]);
}

it('puts the headings back at the levels of the published strip for an editor who does not choose levels', function (): void {
    app()->instance(StripTypes::class, new StripTypes([new Banner]));
    $page = Page::factory()->published()->withStrips([
        storedStrip('banner', ['kop' => ['text' => 'Welkom', 'tag' => 'h3'], 'kaarten' => [['id' => RESTORED_ITEM, 'titel' => ['text' => 'Teams', 'tag' => 'h2']]]], id: 'banner-1'),
    ])->withDraft([])->create();

    $strip = RestoreStrip::run($page, restoredBanner(), 0, chooseLevels: false);

    expect($strip->values['kop'])->toBe(['text' => 'Welkom', 'tag' => 'h3'])
        ->and($strip->values['kaarten'][0]['titel'])->toBe(['text' => 'Teams', 'tag' => 'h2'])
        ->and($strip->values['kaarten'][1]['titel'])->toBe(['text' => 'Nieuw', 'tag' => 'h3']);
});

it('puts the headings back at their default levels for an editor who does not choose levels when the published page does not hold the strip', function (array $published): void {
    app()->instance(StripTypes::class, new StripTypes([new Banner]));
    $page = Page::factory()->published()->withStrips($published)->withDraft([])->create();

    $strip = RestoreStrip::run($page, restoredBanner(), 0, chooseLevels: false);

    expect($strip->values['kop'])->toBe(['text' => 'Welkom', 'tag' => 'h2'])
        ->and(array_column($strip->values['kaarten'], 'titel'))->toBe([['text' => 'Teams', 'tag' => 'h3'], ['text' => 'Nieuw', 'tag' => 'h3']]);
})->with([
    'no strip' => [[]],
    'a strip of another type with its id' => [[storedStrip('hero', ['kop' => ['text' => 'Welkom', 'tag' => 'h3']], id: 'banner-1')]],
]);

it('puts the headings back at the levels that come along for an editor who chooses levels', function (): void {
    app()->instance(StripTypes::class, new StripTypes([new Banner]));
    $page = Page::factory()->published()->withStrips([
        storedStrip('banner', ['kop' => ['text' => 'Welkom', 'tag' => 'h3']], id: 'banner-1'),
    ])->withDraft([])->create();

    $strip = RestoreStrip::run($page, restoredBanner(), 0, chooseLevels: true);

    expect($strip->values['kop'])->toBe(['text' => 'Welkom', 'tag' => 'h1'])
        ->and(array_column($strip->values['kaarten'], 'titel'))->toBe([['text' => 'Teams', 'tag' => 'h3'], ['text' => 'Nieuw', 'tag' => 'h2']]);
});
