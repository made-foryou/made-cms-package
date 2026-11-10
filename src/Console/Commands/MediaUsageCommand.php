<?php

declare(strict_types=1);

namespace Made\CmsPackage\Console\Commands;

use Illuminate\Console\Command;
use Made\CmsPackage\Content\Actions\RebuildMediaUsage;

/**
 * Builds the usage of the media library again from the strips of every page: once on a site that had content before
 * the usage was kept, and after content was written without the CMS (a seeder, an import).
 */
final class MediaUsageCommand extends Command
{
    protected $signature = 'made-cms:media-usage';

    protected $description = 'Build where every file of the Made CMS media library is used again from the strips of every page';

    public function handle(): int
    {
        $rebuilt = RebuildMediaUsage::run();

        $this->components->info((string) __('made-cms-package::console.media_usage.rebuilt', [
            'pages' => trans_choice('made-cms-package::console.media_usage.pages', $rebuilt->pages),
            'places' => trans_choice('made-cms-package::console.media_usage.places', $rebuilt->places),
        ]));

        return self::SUCCESS;
    }
}
