<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\SitemapEntry;
use Made\CmsPackage\Content\Data\SitemapError;
use Made\CmsPackage\Content\Data\SitemapReason;
use Made\CmsPackage\Content\Data\SitemapSources;
use Made\CmsPackage\Content\Data\SitePrefixes;
use Made\CmsPackage\Content\Enums\SitemapErrorReason;
use Made\CmsPackage\Content\Models\Sitemap;
use Made\CmsPackage\Content\Sitemap\SitemapXml;
use Made\CmsPackage\Support\Site;

final class BuildSitemap
{
    use AsAction;

    /**
     * How many sitemaps of a route group stay stored: the newest is served, the ones before it tell what changed.
     */
    public const int KEEP = 10;

    public function __construct(
        private readonly SitemapSources $sources,
    ) {}

    /**
     * Builds the sitemap of the route group with this prefix from its sources and stores it. Every address is absolute
     * from the url of the site with the prefix of the group, as its canonical address. What belongs in it but is left
     * out becomes an error: no address, a rule of robots.txt for Google, or the limits of Google. A sitemap that would
     * be the same as the last one is not stored again, so its moment stays the moment it last changed.
     */
    public function handle(string $prefix, SitemapReason $reason): Sitemap
    {
        $rules = ListGoogleRobotsRules::run();
        $addresses = [];
        $errors = [];

        foreach ($this->sources->sources as $source) {
            foreach ($source->candidates() as $candidate) {
                if ($candidate->path === null) {
                    $errors[] = new SitemapError(null, $candidate->label, SitemapErrorReason::NoAddress);

                    continue;
                }

                $path = SitePrefixes::path($prefix, $candidate->path);

                if ($rules->blocks($path)) {
                    $errors[] = new SitemapError($path, $candidate->label, SitemapErrorReason::Blocked);

                    continue;
                }

                $addresses[] = [$path, $candidate->label, new SitemapEntry(Site::url($path), $candidate->lastModified)];
            }
        }

        $document = (new SitemapXml)->write(array_column($addresses, 2));

        foreach (array_slice($addresses, $document->count) as [$path, $label]) {
            $errors[] = new SitemapError($path, $label, SitemapErrorReason::Full);
        }

        $errors = array_map(fn (SitemapError $error): array => $error->toArray(), $errors);
        $latest = Sitemap::query()->forPrefix($prefix)->newestFirst()->first();

        if ($latest !== null && $latest->xml === $document->xml && $latest->errors === $errors) {
            return $latest;
        }

        return DB::transaction(function () use ($prefix, $reason, $document, $errors): Sitemap {
            $sitemap = Sitemap::query()->create([
                'prefix' => $prefix,
                'xml' => $document->xml,
                'address_count' => $document->count,
                'errors' => $errors,
                'cause' => $reason->cause,
                'page_id' => $reason->pageId,
                'editor_id' => $reason->editorId,
                'built_at' => now(),
            ]);

            $kept = Sitemap::query()->forPrefix($prefix)->newestFirst()->limit(self::KEEP)->pluck('id')->all();
            Sitemap::query()->forPrefix($prefix)->whereKeyNot($kept)->delete();

            return $sitemap;
        });
    }
}
