<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\ListEditableStrips;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
});

it('lists the strips of the working copy in order with the name and the number of fields of their type', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('hero', ['kop' => 'Welkom'], id: 'a'),
        storedStrip('cards', id: 'b'),
    ])->create();

    expect(ListEditableStrips::run($page)->toArray())->toBe([
        ['id' => 'a', 'type' => 'hero', 'label' => 'Hero', 'visible' => true, 'fields' => 6],
        ['id' => 'b', 'type' => 'cards', 'label' => 'Kaarten', 'visible' => true, 'fields' => 1],
    ]);
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

it('leaves out strips of a type that is no longer in the catalogue', function (): void {
    $page = Page::factory()->withDraft([
        storedStrip('oud', ['x' => 1], id: 'a'),
        storedStrip('cards', id: 'b'),
    ])->create();

    expect(array_column(ListEditableStrips::run($page)->toArray(), 'id'))->toBe(['b']);
});

it('lists nothing for a page without strips', function (): void {
    expect(ListEditableStrips::run(Page::factory()->create())->toArray())->toBe([]);
});
