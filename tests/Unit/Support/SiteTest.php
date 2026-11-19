<?php

declare(strict_types=1);

use Made\CmsPackage\Support\Site;

beforeEach(function (): void {
    config(['app.url' => 'https://example.com']);
});

it('keeps an absolute address on the web as it is', function (string $url): void {
    expect(Site::absolute($url))->toBe($url);
})->with([
    'https' => ['https://cdn.example.net/media/01jb/teamfoto.webp'],
    'http' => ['http://example.com/storage/media/01jb/teamfoto.webp'],
    'capitals in the scheme' => ['HTTPS://cdn.example.net/deel.jpg'],
]);

it('makes an address from the root of the site absolute from the url of the site', function (): void {
    expect(Site::absolute('/storage/media/01jb/teamfoto.webp'))->toBe('https://example.com/storage/media/01jb/teamfoto.webp');
});

it('takes the root of the domain for an address from the root, also for a site in a folder of its domain', function (): void {
    config(['app.url' => 'https://example.com/club/']);

    expect(Site::absolute('/club/storage/deel.jpg'))->toBe('https://example.com/club/storage/deel.jpg');
});

it('keeps the port of the url of the site', function (): void {
    config(['app.url' => 'http://127.0.0.1:8000']);

    expect(Site::absolute('/storage/deel.jpg'))->toBe('http://127.0.0.1:8000/storage/deel.jpg');
});

it('gives nothing for an address a shared link cannot open', function (string $url): void {
    expect(Site::absolute($url))->toBeNull();
})->with([
    'empty' => [''],
    'blank' => ['   '],
    'without the scheme' => ['//cdn.example.net/deel.jpg'],
    'another scheme' => ['ftp://example.com/deel.jpg'],
    'a script' => ['javascript:alert(1)'],
    'data' => ['data:image/png;base64,AAAA'],
    'relative to the page' => ['storage/deel.jpg'],
    'without a host' => ['https:///deel.jpg'],
    'a space' => ['/storage/deel foto.jpg'],
    'a line break' => ["/storage/deel.jpg\nSet-Cookie: x"],
]);
