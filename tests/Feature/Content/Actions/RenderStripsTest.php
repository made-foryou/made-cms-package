<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\RenderStrips;
use Made\CmsPackage\Content\Data\EditMode;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\Strips\Teaser;

beforeEach(function (): void {
    useStripTypes();
});

it('renders the visible strips of a page in order as their id, type and rendered fields', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('cards', ['kaarten' => [['titel' => 'Een']]], id: 'a'),
        storedStrip('hero', ['kop' => 'TTV Merwestad', 'knoppen' => [['tekst' => 'Lid worden', 'gaat_naar' => ['url' => '/lid-worden']]]], id: 'b'),
    ])->create();

    expect(RenderStrips::run($page)->toArray())->toBe([
        ['id' => 'a', 'type' => 'cards', 'kaarten' => [['titel' => 'Een']], 'aantal' => 1],
        [
            'id' => 'b',
            'type' => 'hero',
            'label' => null,
            'kop' => 'TTV Merwestad',
            'intro' => null,
            'tekst' => null,
            'actie' => null,
            'knoppen' => [['tekst' => 'Lid worden', 'gaat_naar' => '/lid-worden', 'stijl' => null]],
        ],
    ]);
});

it('leaves out hidden strips', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('cards', id: 'a', visible: false),
        storedStrip('cards', id: 'b'),
    ])->create();

    expect(array_column(RenderStrips::run($page)->toArray(), 'id'))->toBe(['b']);
});

it('leaves out strips of a type that is no longer in the catalogue', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('oud', ['x' => 1], id: 'a'),
        storedStrip('cards', id: 'b'),
    ])->create();

    expect(array_column(RenderStrips::run($page)->toArray(), 'id'))->toBe(['b']);
});

it('renders a page without strips as nothing', function (): void {
    expect(RenderStrips::run(Page::factory()->published()->create())->isEmpty())->toBeTrue();
});

it('renders a link to a page as its current path, and as null once the page is a draft', function (): void {
    $target = Page::factory()->published()->at('/over-ons')->create();
    $page = Page::factory()->published()->withStrips([
        storedStrip('hero', ['knoppen' => [['tekst' => 'Over ons', 'gaat_naar' => ['page' => $target->id]]]], id: 'a'),
    ])->create();

    expect(RenderStrips::run($page)->strips[0]->knoppen[0]['gaat_naar'])->toBe('/over-ons');

    $target->route?->update(['path' => '/wie-we-zijn']);

    expect(RenderStrips::run($page)->strips[0]->knoppen[0]['gaat_naar'])->toBe('/wie-we-zijn');

    $target->update(['status' => 'draft']);

    expect(RenderStrips::run($page)->strips[0]->knoppen[0]['gaat_naar'])->toBeNull();
});

it('renders the published strips, not the draft', function (): void {
    $page = Page::factory()->published()
        ->withStrips([storedStrip('hero', ['kop' => 'Live'], id: 'a')])
        ->withDraft([storedStrip('hero', ['kop' => 'Concept'], id: 'a')])
        ->create();

    expect(RenderStrips::run($page)->strips[0]->kop)->toBe('Live');
});

it('renders the draft instead of the published strips in the edit mode', function (): void {
    app()->instance(EditMode::class, EditMode::on());
    $page = Page::factory()->published()
        ->withStrips([storedStrip('hero', ['kop' => 'Live'], id: 'a')])
        ->withDraft([storedStrip('hero', ['kop' => 'Concept'], id: 'a'), storedStrip('cards', id: 'b')])
        ->create();

    expect(RenderStrips::run($page)->strips[0]->kop)->toBe('Concept')
        ->and(array_column(RenderStrips::run($page)->toArray(), 'id'))->toBe(['a', 'b']);
});

it('renders the published strips in the edit mode when the page has no draft', function (): void {
    app()->instance(EditMode::class, EditMode::on());
    $page = Page::factory()->published()->withStrips([storedStrip('hero', ['kop' => 'Live'], id: 'a')])->create();

    expect(RenderStrips::run($page)->strips[0]->kop)->toBe('Live');
});

it('renders the draft of a page that was never published in the edit mode', function (): void {
    app()->instance(EditMode::class, EditMode::on());
    $page = Page::factory()->withDraft([storedStrip('hero', ['kop' => 'Nieuw'], id: 'a')])->create();

    expect(RenderStrips::run($page)->strips[0]->kop)->toBe('Nieuw');
});

it('leaves hidden strips and unknown types out of the draft in the edit mode', function (): void {
    app()->instance(EditMode::class, EditMode::on());
    $page = Page::factory()->withDraft([
        storedStrip('cards', id: 'a', visible: false),
        storedStrip('oud', ['x' => 1], id: 'b'),
        storedStrip('cards', id: 'c'),
    ])->create();

    expect(array_column(RenderStrips::run($page)->toArray(), 'id'))->toBe(['c']);
});

it('keeps the id and type of a strip when the extra data of the site uses those names', function (): void {
    app()->instance(StripTypes::class, new StripTypes([new Teaser]));
    $page = Page::factory()->published()->withStrips([
        storedStrip('teaser', ['kop' => 'Van de redacteur', 'lees_meer' => ['url' => '/nieuws']], id: 'a'),
    ])->create();

    expect(RenderStrips::run($page)->toArray())->toBe([
        ['id' => 'a', 'type' => 'teaser', 'kop' => 'Uit with', 'lees_meer' => '/nieuws', 'doel' => '/nieuws'],
    ]);
});
