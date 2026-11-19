<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PublicRobotsFile;
use Made\CmsPackage\Content\Data\PublicSiteFiles;
use Made\CmsPackage\Content\Data\PublicSitemapFile;
use Made\CmsPackage\Content\Data\SitePrefixes;
use Made\CmsPackage\Content\Sitemap\SitemapXml;
use Made\CmsPackage\Support\Site;

final class CheckPublicSiteFiles
{
    use AsAction;

    /**
     * Whether the public folder of the site holds a sitemap.xml (of the route group with this prefix) or a robots.txt:
     * the web server serves those before Laravel, so robots never read the files of the CMS. Addresses are compared by
     * their path, so another form of the domain (www, http) of an older sitemap still counts. Reads only.
     */
    public function handle(string $prefix = ''): PublicSiteFiles
    {
        return new PublicSiteFiles($this->sitemap($prefix), $this->robots($prefix));
    }

    private function sitemap(string $prefix): ?PublicSitemapFile
    {
        $file = public_path(ltrim(SitePrefixes::path($prefix, 'sitemap.xml'), '/'));
        $contents = is_file($file) ? file_get_contents($file) : false;
        $modified = is_file($file) ? filemtime($file) : false;

        if ($contents === false || $modified === false) {
            return null;
        }

        $locations = SitemapXml::locations($contents);
        $modifiedAt = CarbonImmutable::createFromTimestamp($modified, date_default_timezone_get());

        if ($locations === null) {
            return new PublicSitemapFile($modifiedAt, null, null);
        }

        $listed = array_map(self::path(...), $locations);
        $pages = array_map(self::path(...), SitemapXml::locations(OpenSitemap::run($prefix)->xml) ?? []);

        return new PublicSitemapFile($modifiedAt, count($locations), count(array_diff($pages, $listed)));
    }

    private function robots(string $prefix): ?PublicRobotsFile
    {
        $file = public_path('robots.txt');
        $contents = is_file($file) ? file_get_contents($file) : false;

        if ($contents === false) {
            return null;
        }

        preg_match_all('/^[ \t]*sitemap[ \t]*:[ \t]*(\S+)/im', $contents, $matches);
        $sitemap = SitePrefixes::path($prefix, 'sitemap.xml');

        return new PublicRobotsFile(array_any(
            $matches[1],
            fn (string $url): bool => self::host($url) === mb_strtolower(Site::host()) && self::path($url) === $sitemap,
        ));
    }

    /**
     * The path of an address, the way the CMS writes it: decoded, lower case and without a slash at the end.
     */
    private static function path(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return '/'.trim(mb_strtolower(rawurldecode(is_string($path) ? $path : '')), '/');
    }

    private static function host(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return preg_replace('/^www\./', '', mb_strtolower(is_string($host) ? $host : '')) ?? '';
    }
}
