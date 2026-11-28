<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\DetermineVisitSource;
use Made\CmsPackage\Content\Data\VisitClues;
use Made\CmsPackage\Content\Enums\VisitSource;

function visitFrom(?string $userAgent, ?string $referrer = null): VisitClues
{
    return new VisitClues($userAgent, $referrer, ['ttvmerwestad.nl', 'site.test']);
}

it('tells where a visit came from', function (?string $userAgent, ?string $referrer, VisitSource $source): void {
    expect(DetermineVisitSource::run(visitFrom($userAgent, $referrer))->source)->toBe($source);
})->with([
    'no user agent' => [null, null, VisitSource::Bot],
    'an empty user agent' => ['  ', null, VisitSource::Bot],
    'googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', null, VisitSource::Googlebot],
    'googlebot for images' => ['Googlebot-Image/1.0', null, VisitSource::Googlebot],
    'googlebot on a phone' => ['Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', null, VisitSource::Googlebot],
    'googlebot, whatever the referrer' => ['Googlebot/2.1', 'https://www.google.com/', VisitSource::Googlebot],
    'bing' => ['Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)', null, VisitSource::Bot],
    'a crawler' => ['Mozilla/5.0 (compatible; YandexBot/3.0)', null, VisitSource::Bot],
    'a spider' => ['Baiduspider+(+http://www.baidu.com/search/spider.htm)', null, VisitSource::Bot],
    'curl' => ['curl/8.7.1', null, VisitSource::Bot],
    'a script' => ['python-requests/2.32.3', null, VisitSource::Bot],
    'a link preview' => ['facebookexternalhit/1.1', null, VisitSource::Bot],
    'a person without a referrer' => [aBrowser(), null, VisitSource::Direct],
    'an empty referrer' => [aBrowser(), '', VisitSource::Direct],
    'a referrer that is no address' => [aBrowser(), 'niet een adres', VisitSource::Direct],
    'google' => [aBrowser(), 'https://www.google.com/', VisitSource::Google],
    'google in the netherlands' => [aBrowser(), 'https://www.google.nl/', VisitSource::Google],
    'google in britain' => [aBrowser(), 'https://www.google.co.uk/', VisitSource::Google],
    'the google app' => [aBrowser(), 'android-app://com.google.android.googlequicksearchbox/', VisitSource::Google],
    'bing for a person' => [aBrowser(), 'https://www.bing.com/', VisitSource::SearchEngine],
    'duckduckgo' => [aBrowser(), 'https://duckduckgo.com/', VisitSource::SearchEngine],
    'ecosia' => [aBrowser(), 'https://www.ecosia.org/', VisitSource::SearchEngine],
    'yahoo' => [aBrowser(), 'https://search.yahoo.com/', VisitSource::SearchEngine],
    'a page of the site' => [aBrowser(), 'https://ttvmerwestad.nl/trainingstijden', VisitSource::Internal],
    'a page of the site with www' => [aBrowser(), 'https://www.ttvmerwestad.nl/trainingstijden', VisitSource::Internal],
    'a page of the site in capitals' => [aBrowser(), 'https://TTVMerwestad.NL/', VisitSource::Internal],
    'another site' => [aBrowser(), 'https://www.facebook.com/', VisitSource::External],
    'a site that only looks like google' => [aBrowser(), 'https://google.example.com/', VisitSource::External],
    'a site that only ends like the site' => [aBrowser(), 'https://notttvmerwestad.nl/', VisitSource::External],
]);

it('keeps the address of the page of the site that linked here', function (): void {
    $origin = DetermineVisitSource::run(visitFrom(aBrowser(), 'https://ttvmerwestad.nl/Teams/Merwestad-1/?tab=uitslagen#boven'));

    expect($origin->source)->toBe(VisitSource::Internal)
        ->and($origin->referrer?->value)->toBe('/teams/merwestad-1');
});

it('reads the address of the page that linked here as a visitor typed it', function (): void {
    expect(DetermineVisitSource::run(visitFrom(aBrowser(), 'https://site.test/caf%C3%A9'))->referrer?->value)->toBe('/café')
        ->and(DetermineVisitSource::run(visitFrom(aBrowser(), 'https://site.test'))->referrer?->value)->toBe('/');
});

it('keeps no address of a page outside the site', function (): void {
    expect(DetermineVisitSource::run(visitFrom(aBrowser(), 'https://www.google.com/search?q=ttv'))->referrer)->toBeNull()
        ->and(DetermineVisitSource::run(visitFrom(aBrowser(), 'https://www.facebook.com/ttv'))->referrer)->toBeNull();
});

it('keeps no address of the page that linked here when it can be no address', function (): void {
    expect(DetermineVisitSource::run(visitFrom(aBrowser(), 'https://site.test/%FF'))->referrer)->toBeNull()
        ->and(DetermineVisitSource::run(visitFrom(aBrowser(), 'https://site.test/%FF'))->source)->toBe(VisitSource::Internal);
});

it('counts the bots the developer adds as bots', function (): void {
    config(['made-cms-package.address_hits.bots' => ['MonitorDienst', 42, '']]);

    expect(DetermineVisitSource::run(visitFrom('Mozilla/5.0 (monitordienst/1.0)'))->source)->toBe(VisitSource::Bot)
        ->and(DetermineVisitSource::run(visitFrom(aBrowser()))->source)->toBe(VisitSource::Direct);
});

it('keeps its own bots when the developer sets something that is no list', function (): void {
    config(['made-cms-package.address_hits.bots' => 'MonitorDienst']);

    expect(DetermineVisitSource::run(visitFrom('curl/8.7.1'))->source)->toBe(VisitSource::Bot)
        ->and(DetermineVisitSource::run(visitFrom('MonitorDienst/1.0'))->source)->toBe(VisitSource::Direct);
});
