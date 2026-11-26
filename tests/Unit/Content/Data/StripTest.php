<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\Strip;

it('reads and writes the stored form of a strip', function (): void {
    $stored = storedStrip('hero', ['kop' => 'Welkom'], visible: false, id: 'a');

    $strip = Strip::fromArray($stored);

    expect($strip->id)->toBe('a')
        ->and($strip->type)->toBe('hero')
        ->and($strip->visible)->toBeFalse()
        ->and($strip->values)->toBe(['kop' => 'Welkom'])
        ->and($strip->toArray())->toBe($stored);
});

it('keeps a strip without values or visibility readable', function (): void {
    $strip = Strip::fromArray(['id' => 'a', 'type' => 'hero']);

    expect($strip->visible)->toBeTrue()
        ->and($strip->values)->toBe([]);
});

it('returns changed copies', function (): void {
    $strip = Strip::fromArray(storedStrip('hero', ['kop' => 'Oud'], id: 'a'));

    $hidden = $strip->withVisibility(false);
    $changed = $strip->withValues(['kop' => 'Nieuw']);
    $renamed = $strip->withId('b');

    expect($strip->visible)->toBeTrue()
        ->and($strip->values)->toBe(['kop' => 'Oud'])
        ->and($strip->id)->toBe('a')
        ->and($hidden->visible)->toBeFalse()
        ->and($hidden->id)->toBe('a')
        ->and($changed->values)->toBe(['kop' => 'Nieuw'])
        ->and($changed->id)->toBe('a')
        ->and($renamed->toArray())->toBe(storedStrip('hero', ['kop' => 'Oud'], id: 'b'));
});
