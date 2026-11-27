<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\ListShownStrips;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Data\ShownStrip;

beforeEach(function (): void {
    useStripTypes();
});

it('gives the strips a visitor sees in order, each with its type: visible and of a type the catalogue has', function (): void {
    $shown = ListShownStrips::run(PageStrips::fromArray([
        storedStrip('cards', id: 'a'),
        storedStrip('hero', visible: false, id: 'b'),
        storedStrip('weg', id: 'c'),
        storedStrip('hero', ['kop' => 'Welkom'], id: 'd'),
    ]));

    expect(array_map(static fn (ShownStrip $strip): array => [$strip->strip->id, $strip->type->key()], $shown))->toBe([
        ['a', 'cards'],
        ['d', 'hero'],
    ])->and($shown[1]->strip->values)->toBe(['kop' => 'Welkom']);
});

it('gives nothing for a page without strips', function (): void {
    expect(ListShownStrips::run(PageStrips::fromArray([])))->toBe([]);
});
