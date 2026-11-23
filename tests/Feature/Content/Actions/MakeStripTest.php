<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\MakeStrip;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Nested\Cards;

it('makes a visible strip of a type with a new id and the defaults of the type in their stored form', function (): void {
    $cards = new Cards;

    $first = MakeStrip::run($cards);
    $second = MakeStrip::run($cards);

    expect($first->type)->toBe('cards')
        ->and($first->visible)->toBeTrue()
        ->and($first->id)->toBeUuid()
        ->and($first->id)->not->toBe($second->id)
        ->and($first->values)->toBe($cards->normalize($cards->defaults()));
});
