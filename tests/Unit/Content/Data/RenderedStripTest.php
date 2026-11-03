<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\RenderedStrip;

it('turns into the id, the type and the rendered values', function (): void {
    $strip = new RenderedStrip('a', 'hero', ['kop' => 'Welkom', 'knoppen' => []]);

    expect($strip->toArray())->toBe(['id' => 'a', 'type' => 'hero', 'kop' => 'Welkom', 'knoppen' => []]);
});

it('keeps its id and type when a value has the same name', function (): void {
    $strip = new RenderedStrip('a', 'hero', ['type' => 'overschreven', 'id' => 'b', 'kop' => 'Welkom']);

    expect($strip->toArray())->toBe(['id' => 'a', 'type' => 'hero', 'kop' => 'Welkom']);
});

it('reads a value as a property, an unknown one as null', function (): void {
    $strip = new RenderedStrip('a', 'hero', ['kop' => 'Welkom', 'label' => null]);

    expect($strip->kop)->toBe('Welkom')
        ->and($strip->onbekend)->toBeNull()
        ->and(isset($strip->kop))->toBeTrue()
        ->and(isset($strip->label))->toBeFalse()
        ->and(isset($strip->onbekend))->toBeFalse();
});
