<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\RenderedStrip;
use Made\CmsPackage\Content\Data\RenderedStrips;

it('turns into a list of strips, ready as a prop', function (): void {
    $strips = new RenderedStrips([
        new RenderedStrip('a', 'hero', ['kop' => 'Welkom']),
        new RenderedStrip('b', 'cards', ['kaarten' => []]),
    ]);

    expect($strips->isEmpty())->toBeFalse()
        ->and($strips->toArray())->toBe([
            ['id' => 'a', 'type' => 'hero', 'kop' => 'Welkom'],
            ['id' => 'b', 'type' => 'cards', 'kaarten' => []],
        ]);
});

it('knows when there is nothing to render', function (): void {
    expect((new RenderedStrips([]))->isEmpty())->toBeTrue()
        ->and((new RenderedStrips([]))->toArray())->toBe([]);
});
