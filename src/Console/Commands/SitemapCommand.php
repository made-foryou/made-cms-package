<?php

declare(strict_types=1);

namespace Made\CmsPackage\Console\Commands;

use Illuminate\Console\Command;
use Made\CmsPackage\Content\Actions\BuildSitemaps;
use Made\CmsPackage\Content\Data\SitemapReason;
use Made\CmsPackage\Content\Data\SitePrefixes;
use Made\CmsPackage\Content\Enums\SitemapCause;

/**
 * Builds the sitemaps again by hand, for instance after an import that did not go through the Actions of the CMS.
 */
final class SitemapCommand extends Command
{
    protected $signature = 'made-cms:sitemap';

    protected $description = 'Build the sitemap of every route group that serves the Made CMS pages again';

    public function handle(): int
    {
        $built = BuildSitemaps::run(new SitemapReason(SitemapCause::Command));

        if ($built === []) {
            $this->components->warn((string) __('made-cms-package::console.sitemap.no_routes'));

            return self::SUCCESS;
        }

        foreach ($built as $sitemap) {
            $this->components->info((string) __('made-cms-package::console.sitemap.'.($sitemap->changed ? 'built' : 'unchanged'), [
                'address' => SitePrefixes::path($sitemap->prefix, 'sitemap.xml'),
                'addresses' => trans_choice('made-cms-package::console.sitemap.addresses', $sitemap->addresses),
                'errors' => trans_choice('made-cms-package::console.sitemap.errors', $sitemap->errors),
            ]));
        }

        return self::SUCCESS;
    }
}
