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
    ]);
}

it('puts a removed strip back in its place with its id, values and visibility', function (): void {
    $page = Page::factory()->withDraft([storedStrip(id: 'a'), storedStrip(id: 'b')])->create();

    $strip = RestoreStrip::run($page, removedHero(), 1);

    expect($strip->toArray())->toBe(removedHero()->toArray())
        ->and(Page::query()->findOrFail($page->id)->draft_strips)->toBe([
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
    RestoreStrip::run($page, $removed, 1);

    expect(CompareDraft::run($page->refresh())->count())->toBe(0);
});

it('drops the draft once a removed strip is put back', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip('cards', id: 'a'), storedStrip('cards', id: 'b')])->create();

    $removed = RemoveStrip::run($page, 'b');

    expect($page->draft_strips)->not->toBeNull();

    RestoreStrip::run($page, $removed, 1);

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBeNull();
});

it('checks the values against the strip type and drops what the type does not know', function (): void {
    $page = Page::factory()->withDraft([])->create();

    $strip = RestoreStrip::run($page, new Strip('hero-1', 'hero', true, ['kop' => 'Welkom', 'onbekend' => 'weg', 'knoppen' => [['tekst' => 'Lid worden', 'gaat_naar' => ['url' => '/lid']]]]), 0);

    expect($strip->values)->not->toHaveKey('onbekend')
        ->and($strip->values['kop'])->toBe('Welkom');

    expect(fn (): mixed => RestoreStrip::run($page, new Strip('hero-2', 'hero', true, ['kop' => null]), 0))
        ->toThrow(ValidationException::class);
});

it('dispatches the event of a strip that comes onto the page', function (): void {
    Event::fake([StripAdded::class]);
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();

    RestoreStrip::run($page, removedHero(), 0);

    Event::assertDispatched(StripAdded::class, fn (StripAdded $event): bool => $event->page->is($page)
        && $event->strip->id === 'hero-1'
        && $event->position === 0);
});

it('refuses a strip that is still on the page', function (): void {
    $page = Page::factory()->withDraft([removedHero()->toArray()])->create();

    expect(fn (): mixed => RestoreStrip::run($page, removedHero(), 0))->toThrow(StripAlreadyOnPage::class);
});

it('refuses a position outside the page', function (int $position): void {
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();

    expect(fn (): mixed => RestoreStrip::run($page, removedHero(), $position))->toThrow(InvalidStripPosition::class);
})->with([-1, 2]);

it('puts back the published copy of a strip whose type left the catalogue, whatever values come along', function (): void {
    $old = storedStrip('oud', ['x' => 'Sponsoren'], id: 'old');
    $page = Page::factory()->published()->withStrips([$old, storedStrip(id: 'a')])->withDraft([$old, storedStrip(id: 'a'), storedStrip(id: 'new')])->create();

    RemoveStrip::run($page, 'old');
    $strip = RestoreStrip::run($page, new Strip('old', 'oud', true, ['x' => 'iets anders']), 0);

    expect($strip->toArray())->toBe($old)
        ->and(Page::query()->findOrFail($page->id)->draft_strips)->toBe([$old, storedStrip(id: 'a'), storedStrip(id: 'new')]);
});

it('refuses a strip of an unknown type that the published page does not hold', function (): void {
    $page = Page::factory()->withDraft([])->create();

    expect(fn (): mixed => RestoreStrip::run($page, new Strip('old', 'oud', true, ['x' => 1]), 0))->toThrow(UnknownStripType::class);
});
