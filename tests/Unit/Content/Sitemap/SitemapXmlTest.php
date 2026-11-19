<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Data\SitemapEntry;
use Made\CmsPackage\Content\Sitemap\SitemapXml;

it('writes every address with the moment it last changed as a W3C date', function (): void {
    $document = (new SitemapXml)->write([
        new SitemapEntry('https://ttvmerwestad.nl/', CarbonImmutable::parse('2026-11-06 11:20:00', 'Europe/Amsterdam')),
        new SitemapEntry('https://ttvmerwestad.nl/teams', CarbonImmutable::parse('2026-07-01 08:05:09', 'Europe/Amsterdam')),
    ]);

    expect($document->count)->toBe(2)
        ->and($document->xml)->toBe(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
              <url>
                <loc>https://ttvmerwestad.nl/</loc>
                <lastmod>2026-11-06T11:20:00+01:00</lastmod>
              </url>
              <url>
                <loc>https://ttvmerwestad.nl/teams</loc>
                <lastmod>2026-07-01T08:05:09+02:00</lastmod>
              </url>
            </urlset>

            XML);
});

it('leaves the moment out when an address has none', function (): void {
    $document = (new SitemapXml)->write([new SitemapEntry('https://ttvmerwestad.nl/teams', null)]);

    expect($document->xml)->toContain("<loc>https://ttvmerwestad.nl/teams</loc>\n  </url>")
        ->and($document->xml)->not->toContain('lastmod');
});

it('escapes an address for xml', function (): void {
    $document = (new SitemapXml)->write([new SitemapEntry("https://ttvmerwestad.nl/a?b=1&c='<d>\"", null)]);

    expect($document->xml)->toContain('<loc>https://ttvmerwestad.nl/a?b=1&amp;c=&apos;&lt;d&gt;&quot;</loc>');
});

it('writes an empty list of addresses when there is nothing to list', function (): void {
    $document = (new SitemapXml)->write([]);

    expect($document->count)->toBe(0)
        ->and($document->xml)->toBe("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n</urlset>\n");
});

it('holds at most the number of addresses Google reads in one sitemap', function (): void {
    expect(SitemapXml::MAX_ADDRESSES)->toBe(50_000)
        ->and(SitemapXml::MAX_BYTES)->toBe(50 * 1024 * 1024);

    $entries = array_map(fn (int $number): SitemapEntry => new SitemapEntry('https://ttvmerwestad.nl/'.$number, null), range(1, 3));

    $document = (new SitemapXml(maxAddresses: 2))->write($entries);

    expect($document->count)->toBe(2)
        ->and($document->xml)->toContain('/1</loc>')
        ->and($document->xml)->toContain('/2</loc>')
        ->and($document->xml)->not->toContain('/3</loc>');
});

it('stops before an address that would make the sitemap larger than Google reads', function (): void {
    $entries = array_map(fn (int $number): SitemapEntry => new SitemapEntry('https://ttvmerwestad.nl/'.$number, null), range(1, 3));
    $two = strlen((new SitemapXml)->write(array_slice($entries, 0, 2))->xml);

    $exact = (new SitemapXml(maxBytes: $two))->write($entries);
    $short = (new SitemapXml(maxBytes: $two - 1))->write($entries);

    expect($exact->count)->toBe(2)
        ->and(strlen($exact->xml))->toBe($two)
        ->and($short->count)->toBe(1)
        ->and(strlen($short->xml))->toBeLessThan($two);
});

it('reads the addresses back from a sitemap, with or without its namespace', function (): void {
    $written = (new SitemapXml)->write([
        new SitemapEntry('https://ttvmerwestad.nl/', null),
        new SitemapEntry('https://ttvmerwestad.nl/a?b=1&c=2', null),
    ])->xml;

    expect(SitemapXml::locations($written))->toBe(['https://ttvmerwestad.nl/', 'https://ttvmerwestad.nl/a?b=1&c=2'])
        ->and(SitemapXml::locations('<urlset><url><loc> https://ttvmerwestad.nl/teams </loc></url></urlset>'))->toBe(['https://ttvmerwestad.nl/teams']);
});

it('reads no addresses from what is no xml', function (string $xml): void {
    expect(SitemapXml::locations($xml))->toBeNull();
})->with([
    'nothing' => [''],
    'broken' => ['<urlset><url><loc>https://ttvmerwestad.nl/</loc></url>'],
    'html' => ['<!doctype html><p>Niet gevonden'],
]);

it('never loads an entity from outside the file, and leaves an empty address out', function (): void {
    $xml = '<?xml version="1.0"?><!DOCTYPE urlset [<!ENTITY secret SYSTEM "file:///etc/hosts">]>'
        .'<urlset><url><loc>&secret;</loc></url><url><loc> </loc></url></urlset>';

    expect(SitemapXml::locations($xml))->toBe([]);
});
