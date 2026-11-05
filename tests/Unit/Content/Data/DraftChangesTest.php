<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\DraftChanges;

it('counts every changed strip', function (): void {
    $changes = new DraftChanges(
        live: true,
        added: ['a'],
        changed: ['b', 'c'],
        removed: ['d'],
        moved: ['e'],
        publishable: true,
        discardable: true,
    );

    expect($changes->count())->toBe(5);
});

it('has no changes by default', function (): void {
    $changes = new DraftChanges(live: false);

    expect($changes->count())->toBe(0)
        ->and($changes->publishable)->toBeFalse()
        ->and($changes->discardable)->toBeFalse();
});
