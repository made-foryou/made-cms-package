<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Sitemap\RobotsPattern;

it('reads a rule the way Google does', function (string $pattern, string $path, bool $matches): void {
    expect((new RobotsPattern($pattern))->matches($path))->toBe($matches);
})->with([
    'the root matches everything' => ['/', '/fish/salmon.html', true],
    'a star after the root as well' => ['/*', '/fish', true],
    'a dollar after the root only the root' => ['/$', '/', true],
    'a dollar after the root not a page' => ['/$', '/fish', false],
    'the start of a path' => ['/fish', '/fish.html', true],
    'the start of a folder' => ['/fish', '/fish/salmon.html', true],
    'the start of a longer word' => ['/fishheads', '/fishheads/yummy.html', true],
    'with a query' => ['/fish', '/fish.php?id=anything', true],
    'not other capitals' => ['/fish', '/Fish.asp', false],
    'not in the middle' => ['/fish', '/catfish', false],
    'not in a query' => ['/fish', '/?id=fish', false],
    'not deeper' => ['/fish', '/desert/fish', false],
    'a star at the end changes nothing' => ['/fish*', '/fishheads', true],
    'a folder with its slash' => ['/fish/', '/fish/salmon.htm', true],
    'a folder not without its slash' => ['/fish/', '/fish', false],
    'a folder not elsewhere' => ['/fish/', '/animals/fish/', false],
    'a star in the middle' => ['/*.php', '/folder/filename.php?parameters', true],
    'a star in the middle further on' => ['/*.php', '/folder/any.php.file.html', true],
    'a star in the middle not the root' => ['/*.php', '/', false],
    'a star in the middle not other capitals' => ['/*.php', '/windows.PHP', false],
    'a dollar at the end' => ['/*.php$', '/folder/filename.php', true],
    'a dollar at the end not with a query' => ['/*.php$', '/filename.php?parameters', false],
    'a dollar at the end not with more' => ['/*.php$', '/filename.php5', false],
    'a star before a word' => ['/fish*.php', '/fishheads/catfish.php?parameters', true],
    'a star before a word not other capitals' => ['/fish*.php', '/Fish.PHP', false],
    'a dollar in the middle is a character' => ['/a$b', '/a$b', true],
    'other characters of a regular expression' => ['/(a)+', '/(a)+', true],
    'other characters of a regular expression not as such' => ['/(a)+', '/aa', false],
]);

it('matches nothing without a pattern', function (string $pattern): void {
    expect((new RobotsPattern($pattern))->matches('/'))->toBeFalse()
        ->and((new RobotsPattern($pattern))->matches('/fish'))->toBeFalse();
})->with(['empty' => [''], 'spaces' => ['   ']]);

it('keeps the pattern as it was given, without the spaces around it', function (): void {
    expect((new RobotsPattern('  /zoeken$ '))->value)->toBe('/zoeken$');
});
