<?php

declare(strict_types=1);

use Made\CmsPackage\Support\PagePath;

it('writes a path one way, however the request gives it', function (string $path, string $normalised): void {
    expect(PagePath::normalise($path))->toBe($normalised);
})->with([
    'home' => ['/', '/'],
    'home without slash' => ['', '/'],
    'leading slash' => ['/lid-worden', '/lid-worden'],
    'without slashes' => ['lid-worden', '/lid-worden'],
    'trailing slash' => ['/lid-worden/', '/lid-worden'],
    'nested' => ['teams/heren-1', '/teams/heren-1'],
]);
