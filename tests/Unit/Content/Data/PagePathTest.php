<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\PagePath;

it('normalises an address to lowercase with one leading slash and no trailing slash', function (string $raw, string $expected): void {
    expect(PagePath::fromString($raw)->value)->toBe($expected);
})->with([
    'home' => ['/', '/'],
    'empty' => ['', '/'],
    'whitespace' => ['  ', '/'],
    'without slash' => ['over-ons', '/over-ons'],
    'trailing slash' => ['/over-ons/', '/over-ons'],
    'nested' => ['over-ons/team', '/over-ons/team'],
    'repeated slashes' => ['//over-ons///team//', '/over-ons/team'],
    'surrounding whitespace' => [' /over-ons ', '/over-ons'],
    'uppercase' => ['/Over-Ons/TEAM', '/over-ons/team'],
    'uppercase with accents' => ['/CAFÉ', '/café'],
]);

it('knows the home address', function (): void {
    expect(PagePath::fromString('')->isHome())->toBeTrue()
        ->and(PagePath::fromString('/over-ons')->isHome())->toBeFalse();
});

it('gives the address of a child', function (string $parent, string $slug, string $expected): void {
    expect(PagePath::fromString($parent)->child($slug)->value)->toBe($expected);
})->with([
    'under home' => ['/', 'team', '/team'],
    'nested' => ['/over-ons', 'team', '/over-ons/team'],
    'uppercase' => ['/over-ons', 'TEAM', '/over-ons/team'],
    'with slashes' => ['/over-ons', '/team/', '/over-ons/team'],
]);
