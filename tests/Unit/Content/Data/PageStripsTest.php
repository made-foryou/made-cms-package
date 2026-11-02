<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Data\Strip;

/**
 * @return list<string>
 */
function stripIds(PageStrips $strips): array
{
    return array_map(static fn (Strip $strip): string => $strip->id, $strips->strips);
}

function threeStrips(): PageStrips
{
    return PageStrips::fromArray([
        storedStrip(id: 'a'),
        storedStrip(id: 'b'),
        storedStrip(id: 'c'),
    ]);
}

it('reads and writes the stored list', function (): void {
    $stored = [storedStrip('hero', ['kop' => 'Welkom'], id: 'a'), storedStrip('oud', ['x' => 1], visible: false, id: 'b')];

    $strips = PageStrips::fromArray($stored);

    expect($strips->count())->toBe(2)
        ->and($strips->toArray())->toBe($stored);
});

it('finds a strip and its position', function (): void {
    $strips = threeStrips();

    expect($strips->find('b')?->id)->toBe('b')
        ->and($strips->find('x'))->toBeNull()
        ->and($strips->positionOf('c'))->toBe(2)
        ->and($strips->positionOf('x'))->toBeNull();
});

it('inserts a strip at a position', function (): void {
    $strips = threeStrips();
    $new = Strip::fromArray(storedStrip(id: 'n'));

    expect(stripIds($strips->insert($new, 0)))->toBe(['n', 'a', 'b', 'c'])
        ->and(stripIds($strips->insert($new, 1)))->toBe(['a', 'n', 'b', 'c'])
        ->and(stripIds($strips->insert($new, 3)))->toBe(['a', 'b', 'c', 'n'])
        ->and(stripIds($strips))->toBe(['a', 'b', 'c']);
});

it('removes a strip', function (): void {
    $strips = threeStrips();

    expect(stripIds($strips->remove('b')))->toBe(['a', 'c'])
        ->and(stripIds($strips))->toBe(['a', 'b', 'c']);
});

it('moves a strip to a position', function (): void {
    $strips = threeStrips();

    expect(stripIds($strips->move('a', 2)))->toBe(['b', 'c', 'a'])
        ->and(stripIds($strips->move('c', 0)))->toBe(['c', 'a', 'b'])
        ->and(stripIds($strips->move('b', 1)))->toBe(['a', 'b', 'c'])
        ->and(stripIds($strips))->toBe(['a', 'b', 'c']);
});

it('replaces a strip in place', function (): void {
    $strips = threeStrips();

    $replaced = $strips->replace(Strip::fromArray(storedStrip(values: ['titel' => 'Nieuw'], id: 'b')));

    expect(stripIds($replaced))->toBe(['a', 'b', 'c'])
        ->and($replaced->find('b')?->values)->toBe(['titel' => 'Nieuw'])
        ->and($strips->find('b')?->values)->toBe([]);
});
