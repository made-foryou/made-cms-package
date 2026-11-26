<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\CopyStrip;
use Made\CmsPackage\Content\Data\Strip;

it('copies a strip as it is stored under a new id', function (): void {
    $values = ['kop' => 'Welkom', 'kaarten' => [['id' => 'item-1', 'titel' => 'Een']]];
    $strip = Strip::fromArray(storedStrip('hero', $values, visible: false, id: 'a'));

    $first = CopyStrip::run($strip);
    $second = CopyStrip::run($strip);

    expect($first->id)->toBeUuid()
        ->and($first->id)->not->toBe('a')
        ->and($first->id)->not->toBe($second->id)
        ->and($first->type)->toBe('hero')
        ->and($first->visible)->toBeFalse()
        ->and($first->values)->toBe($values);
});

it('copies a strip whose type left the catalogue with its data', function (): void {
    $strip = Strip::fromArray(storedStrip('vervallen', ['tekst' => 'Bewaard'], id: 'a'));

    expect(CopyStrip::run($strip)->values)->toBe(['tekst' => 'Bewaard']);
});
