<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\ListEditableStrips;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\Strips\Banner;

beforeEach(function (): void {
    useStripTypes();
});

it('lists the strips of the working copy in order with the name, the number of fields and a summary', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('hero', ['kop' => 'TTV Merwestad'], id: 'a'),
        storedStrip('cards', ['kaarten' => [['titel' => 'Trainen'], ['titel' => 'Spelen']]], id: 'b'),
        storedStrip('cards', id: 'c'),
    ])->create();

    expect(ListEditableStrips::run($page)->toArray())->toBe([
        ['id' => 'a', 'type' => 'hero', 'label' => 'Hero', 'visible' => true, 'known' => true, 'fields' => 6, 'summary' => 'TTV Merwestad', 'headings' => []],
        ['id' => 'b', 'type' => 'cards', 'label' => 'Kaarten', 'visible' => true, 'known' => true, 'fields' => 1, 'summary' => 'Trainen, Spelen', 'headings' => []],
        ['id' => 'c', 'type' => 'cards', 'label' => 'Kaarten', 'visible' => true, 'known' => true, 'fields' => 1, 'summary' => null, 'headings' => []],
    ]);
});

it('sums up a missing field of a strip as its default', function (): void {
    $page = Page::factory()->withDraft([storedStrip('hero', id: 'a')])->create();

    expect(ListEditableStrips::run($page)->strips[0]->summary)->toBe('Welkom');
});

it('lists the draft instead of the published strips', function (): void {
    $page = Page::factory()->published()
        ->withStrips([storedStrip('hero', id: 'a')])
        ->withDraft([storedStrip('cards', id: 'b'), storedStrip('hero', id: 'a')])
        ->create();

    expect(array_column(ListEditableStrips::run($page)->toArray(), 'id'))->toBe(['b', 'a']);
});

it('lists the draft of a page that was never published', function (): void {
    $page = Page::factory()->withDraft([storedStrip('hero', id: 'a')])->create();

    expect(array_column(ListEditableStrips::run($page)->toArray(), 'id'))->toBe(['a']);
});

it('keeps hidden strips, marked as hidden', function (): void {
    $page = Page::factory()->withDraft([storedStrip('cards', id: 'a', visible: false)])->create();

    expect(ListEditableStrips::run($page)->strips[0]->visible)->toBeFalse();
});

it('keeps strips of a type that is no longer in the catalogue in their place, marked as unknown', function (): void {
    $page = Page::factory()->withDraft([
        storedStrip('cards', id: 'a'),
        storedStrip('oud', ['x' => 'Sponsoren'], id: 'b', visible: false),
        storedStrip('cards', id: 'c'),
    ])->create();

    $strips = ListEditableStrips::run($page)->toArray();

    expect(array_column($strips, 'id'))->toBe(['a', 'b', 'c'])
        ->and($strips[1])->toBe(['id' => 'b', 'type' => 'oud', 'label' => 'oud', 'visible' => false, 'known' => false, 'fields' => 0, 'summary' => null, 'headings' => []]);
});

it('names the fields of a strip that are headings, so the edit shell finds a heading at "Geen kop" on the site', function (): void {
    app()->instance(StripTypes::class, new StripTypes([new Banner]));
    $page = Page::factory()->withDraft([storedStrip('banner', id: 'a')])->create();

    expect(ListEditableStrips::run($page)->strips[0]->headings)->toBe([
        ['path' => 'kop', 'list' => null],
        ['path' => 'kaarten.*.titel', 'list' => 'Kaarten'],
    ]);
});

it('lists nothing for a page without strips', function (): void {
    expect(ListEditableStrips::run(Page::factory()->create())->toArray())->toBe([]);
});
