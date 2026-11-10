<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\CompareDraft;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\Strips\Banner;

beforeEach(function (): void {
    useStripTypes();
});

/**
 * A published page with the strips a, b and c and the given draft.
 *
 * @param  list<array<string, mixed>>|null  $draft
 */
function pageWithDraft(?array $draft): Page
{
    $factory = Page::factory()->published()->withStrips([
        storedStrip('hero', ['kop' => 'Welkom'], id: 'a'),
        storedStrip('cards', ['kop' => 'Teams'], id: 'b'),
        storedStrip('cards', ['kop' => 'Agenda'], id: 'c'),
    ]);

    return ($draft === null ? $factory : $factory->withDraft($draft))->create();
}

it('finds nothing to publish or discard on a live page without a draft', function (): void {
    $changes = CompareDraft::run(pageWithDraft(null));

    expect($changes->live)->toBeTrue()
        ->and($changes->count())->toBe(0)
        ->and($changes->publishable)->toBeFalse()
        ->and($changes->discardable)->toBeFalse();
});

it('lets a page that was never published go live without changes', function (): void {
    $changes = CompareDraft::run(Page::factory()->create());

    expect($changes->live)->toBeFalse()
        ->and($changes->count())->toBe(0)
        ->and($changes->publishable)->toBeTrue()
        ->and($changes->discardable)->toBeFalse();
});

it('counts a strip with other values as changed', function (): void {
    $changes = CompareDraft::run(pageWithDraft([
        storedStrip('hero', ['kop' => 'Welkom bij TTV'], id: 'a'),
        storedStrip('cards', ['kop' => 'Teams'], id: 'b'),
        storedStrip('cards', ['kop' => 'Agenda'], id: 'c'),
    ]));

    expect($changes->changed)->toBe(['a'])
        ->and($changes->count())->toBe(1)
        ->and($changes->publishable)->toBeTrue()
        ->and($changes->discardable)->toBeTrue();
});

it('counts a hidden strip as changed', function (): void {
    $changes = CompareDraft::run(pageWithDraft([
        storedStrip('hero', ['kop' => 'Welkom'], id: 'a'),
        storedStrip('cards', ['kop' => 'Teams'], visible: false, id: 'b'),
        storedStrip('cards', ['kop' => 'Agenda'], id: 'c'),
    ]));

    expect($changes->changed)->toBe(['b'])
        ->and($changes->count())->toBe(1);
});

it('counts added and removed strips', function (): void {
    $changes = CompareDraft::run(pageWithDraft([
        storedStrip('hero', ['kop' => 'Welkom'], id: 'a'),
        storedStrip('cards', ['kop' => 'Nieuws'], id: 'd'),
        storedStrip('cards', ['kop' => 'Agenda'], id: 'c'),
    ]));

    expect($changes->added)->toBe(['d'])
        ->and($changes->removed)->toBe(['b'])
        ->and($changes->moved)->toBe([])
        ->and($changes->count())->toBe(2);
});

it('counts one strip moved to the top once', function (): void {
    $changes = CompareDraft::run(pageWithDraft([
        storedStrip('cards', ['kop' => 'Agenda'], id: 'c'),
        storedStrip('hero', ['kop' => 'Welkom'], id: 'a'),
        storedStrip('cards', ['kop' => 'Teams'], id: 'b'),
    ]));

    expect($changes->moved)->toBe(['c'])
        ->and($changes->count())->toBe(1);
});

it('counts two strips that swapped places once', function (): void {
    $changes = CompareDraft::run(pageWithDraft([
        storedStrip('cards', ['kop' => 'Teams'], id: 'b'),
        storedStrip('hero', ['kop' => 'Welkom'], id: 'a'),
        storedStrip('cards', ['kop' => 'Agenda'], id: 'c'),
    ]));

    expect($changes->moved)->toHaveCount(1)
        ->and($changes->count())->toBe(1);
});

it('counts a strip that moved and changed once', function (): void {
    $changes = CompareDraft::run(pageWithDraft([
        storedStrip('cards', ['kop' => 'Agenda deze week'], id: 'c'),
        storedStrip('hero', ['kop' => 'Welkom'], id: 'a'),
        storedStrip('cards', ['kop' => 'Teams'], id: 'b'),
    ]));

    expect($changes->changed)->toBe(['c'])
        ->and($changes->moved)->toBe([])
        ->and($changes->count())->toBe(1);
});

it('finds no changes in a draft that equals the published strips', function (): void {
    $changes = CompareDraft::run(pageWithDraft([
        storedStrip('hero', ['kop' => 'Welkom'], id: 'a'),
        storedStrip('cards', ['kop' => 'Teams'], id: 'b'),
        storedStrip('cards', ['kop' => 'Agenda'], id: 'c'),
    ]));

    expect($changes->count())->toBe(0)
        ->and($changes->publishable)->toBeFalse()
        ->and($changes->discardable)->toBeFalse();
});

it('ignores the order of the keys and the ids of items', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('cards', ['kop' => 'Teams', 'kaarten' => [['id' => 'een', 'titel' => 'Heren 1', 'tekst' => 'Zaterdag']]], id: 'a'),
    ])->withDraft([
        storedStrip('cards', ['kaarten' => [['tekst' => 'Zaterdag', 'id' => 'twee', 'titel' => 'Heren 1']], 'kop' => 'Teams'], id: 'a'),
    ])->create();

    expect(CompareDraft::run($page)->count())->toBe(0);
});

it('counts items in another order as a change', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('cards', ['kaarten' => [['id' => 'een', 'titel' => 'Heren 1'], ['id' => 'twee', 'titel' => 'Dames 1']]], id: 'a'),
    ])->withDraft([
        storedStrip('cards', ['kaarten' => [['id' => 'twee', 'titel' => 'Dames 1'], ['id' => 'een', 'titel' => 'Heren 1']]], id: 'a'),
    ])->create();

    expect(CompareDraft::run($page)->changed)->toBe(['a']);
});

it('counts a missing field as its default, as the strip renders it', function (): void {
    $defaults = useStripTypes()->find('hero')->defaults();
    $page = Page::factory()->published()
        ->withStrips([storedStrip('hero', ['kop' => 'Welkom'], id: 'a'), storedStrip('hero', [], id: 'b')])
        ->withDraft([storedStrip('hero', $defaults, id: 'a'), storedStrip('hero', [...$defaults, 'kop' => 'Anders'], id: 'b')])
        ->create();

    expect(CompareDraft::run($page)->changed)->toBe(['b']);
});

it('compares the values of a strip whose type left the catalogue as they are', function (): void {
    $page = Page::factory()->published()
        ->withStrips([storedStrip('oud', ['x' => 1], id: 'a')])
        ->withDraft([storedStrip('oud', ['x' => 1, 'y' => null], id: 'a')])
        ->create();

    expect(CompareDraft::run($page)->changed)->toBe(['a']);
});

it('counts only another level of a heading as a change, not the form it was stored in', function (mixed $published, mixed $draft, bool $changed): void {
    app()->instance(StripTypes::class, new StripTypes([new Banner]));
    $page = Page::factory()->published()
        ->withStrips([storedStrip('banner', ['kop' => $published], id: 'a')])
        ->withDraft([storedStrip('banner', ['kop' => $draft], id: 'a')])
        ->create();

    expect(CompareDraft::run($page)->changed)->toBe($changed ? ['a'] : []);
})->with([
    'another level' => [['text' => 'Welkom', 'tag' => 'h2'], ['text' => 'Welkom', 'tag' => 'h3'], true],
    'the main heading' => ['Welkom', ['text' => 'Welkom', 'tag' => 'h1'], true],
    'another text' => [['text' => 'Welkom', 'tag' => 'h2'], ['text' => 'Hallo', 'tag' => 'h2'], true],
    'a text stored before the field was a heading' => ['Welkom', ['text' => 'Welkom', 'tag' => 'h2'], false],
    'a level the field no longer offers' => [['text' => 'Welkom', 'tag' => 'h5'], ['text' => 'Welkom', 'tag' => 'h2'], false],
    'a heading emptied at its default level' => [null, ['text' => null, 'tag' => 'h2'], false],
    'a level chosen before the text' => [null, ['text' => null, 'tag' => 'h3'], true],
]);

it('counts a heading in a card that only changes its level as a change', function (): void {
    app()->instance(StripTypes::class, new StripTypes([new Banner]));
    $page = Page::factory()->published()
        ->withStrips([storedStrip('banner', ['kop' => 'Welkom', 'kaarten' => [['id' => 'k1', 'titel' => 'Teams']]], id: 'a')])
        ->withDraft([storedStrip('banner', ['kop' => 'Welkom', 'kaarten' => [['id' => 'k1', 'titel' => ['text' => 'Teams', 'tag' => 'h2']]]], id: 'a')])
        ->create();

    expect(CompareDraft::run($page)->changed)->toBe(['a']);
});
