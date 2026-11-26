<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Data\VersionChanges;

it('counts every strip a publication changed', function (): void {
    $changes = new VersionChanges(
        first: false,
        added: ['a'],
        changed: ['b', 'c'],
        moved: ['d'],
        removed: [new Strip('e', 'cards', true, [])],
    );

    expect($changes->count())->toBe(5);
});

it('changes nothing by default', function (): void {
    $changes = new VersionChanges(first: true);

    expect($changes->count())->toBe(0)
        ->and($changes->added)->toBe([])
        ->and($changes->removed)->toBe([]);
});

it('is stored as facts and read back', function (): void {
    $changes = new VersionChanges(
        first: false,
        added: ['a'],
        changed: ['b'],
        moved: ['c'],
        removed: [new Strip('d', 'hero', false, ['kop' => 'Weg'])],
    );

    expect($changes->toArray())->toBe([
        'first' => false,
        'added' => ['a'],
        'changed' => ['b'],
        'moved' => ['c'],
        'removed' => [['id' => 'd', 'type' => 'hero', 'visible' => false, 'values' => ['kop' => 'Weg']]],
    ])->and(VersionChanges::fromArray($changes->toArray()))->toEqual($changes);
});

it('reads what it does not know as no change', function (): void {
    $changes = VersionChanges::fromArray(['added' => 'a', 'changed' => [1, 'b', null], 'removed' => ['kapot']]);

    expect($changes->first)->toBeFalse()
        ->and($changes->added)->toBe([])
        ->and($changes->changed)->toBe(['b'])
        ->and($changes->moved)->toBe([])
        ->and($changes->removed)->toBe([]);
});
