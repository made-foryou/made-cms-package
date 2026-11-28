<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\VisitClues;
use Made\CmsPackage\Content\Data\VisitOrigin;
use Made\CmsPackage\Content\Enums\VisitSource;
use Made\CmsPackage\Content\Exceptions\InvalidPagePath;
use Made\CmsPackage\Support\ConfiguredStrings;

/**
 * The one place that says where a visit came from: first the user agent (Googlebot, another bot), then the referrer
 * (Google, another search engine, the site itself, another site, or none). Googlebot is who calls itself so: the
 * counts are an indication, not a check.
 */
final class DetermineVisitSource
{
    use AsAction;

    /**
     * Parts of the user agent of crawlers, scripts and link previews, whatever their capitals. The developer adds to
     * them in `address_hits.bots`.
     */
    public const array BOTS = [
        'bot',
        'crawl',
        'spider',
        'slurp',
        'curl',
        'wget',
        'python',
        'go-http-client',
        'java/',
        'okhttp',
        'axios',
        'node-fetch',
        'scrapy',
        'httpclient',
        'libwww',
        'headless',
        'lighthouse',
        'facebookexternalhit',
        'whatsapp',
        'mediapartners-google',
    ];

    /**
     * The hosts of the search engines other than Google, a sub-domain included.
     */
    public const array SEARCH_ENGINES = [
        'bing.com',
        'duckduckgo.com',
        'yahoo.com',
        'ecosia.org',
        'startpage.com',
        'qwant.com',
        'search.brave.com',
        'yandex.com',
        'yandex.ru',
        'baidu.com',
        'ask.com',
    ];

    /**
     * Google in the address bar of the Google app on Android, which sends itself as the referrer.
     */
    private const string GOOGLE_APP = 'com.google.android.googlequicksearchbox';

    public function handle(VisitClues $clues): VisitOrigin
    {
        $userAgent = mb_strtolower(trim((string) $clues->userAgent));

        if ($userAgent === '') {
            return new VisitOrigin(VisitSource::Bot);
        }

        if (str_contains($userAgent, 'googlebot')) {
            return new VisitOrigin(VisitSource::Googlebot);
        }

        foreach ([...self::BOTS, ...ConfiguredStrings::of('made-cms-package.address_hits.bots')] as $bot) {
            if (str_contains($userAgent, mb_strtolower($bot))) {
                return new VisitOrigin(VisitSource::Bot);
            }
        }

        return self::fromReferrer((string) $clues->referrer, $clues->ownHosts);
    }

    /**
     * @param  list<string>  $ownHosts
     */
    private static function fromReferrer(string $referrer, array $ownHosts): VisitOrigin
    {
        $parts = parse_url(trim($referrer));
        $host = is_array($parts) && isset($parts['host']) ? self::bare($parts['host']) : '';

        if ($host === '') {
            return new VisitOrigin(VisitSource::Direct);
        }

        if (in_array($host, array_map(self::bare(...), $ownHosts), true)) {
            return new VisitOrigin(VisitSource::Internal, self::path($parts['path'] ?? '/'));
        }

        if ($host === self::GOOGLE_APP || preg_match('/(^|\.)google\.[a-z]{2,3}(\.[a-z]{2})?$/', $host) === 1) {
            return new VisitOrigin(VisitSource::Google);
        }

        foreach (self::SEARCH_ENGINES as $engine) {
            if ($host === $engine || str_ends_with($host, '.'.$engine)) {
                return new VisitOrigin(VisitSource::SearchEngine);
            }
        }

        return new VisitOrigin(VisitSource::External);
    }

    /**
     * The host in small letters without "www.", so www.site.nl and site.nl are the same site.
     */
    private static function bare(string $host): string
    {
        $host = mb_strtolower(trim($host));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * The path of the page that linked here as an address of the site, or null when it can be none.
     */
    private static function path(string $path): ?PagePath
    {
        try {
            return PagePath::fromString(rawurldecode($path));
        } catch (InvalidPagePath) {
            return null;
        }
    }
}
