<?php

declare(strict_types=1);

namespace Made\CmsPackage\Console\Commands;

use Illuminate\Console\Command;
use Made\CmsPackage\Content\Actions\MakeMissingThumbnails;

/**
 * Makes the thumbnails of the photos of the media library that have none: once on a site that had photos before the
 * library made thumbnails.
 */
final class MediaThumbnailsCommand extends Command
{
    protected $signature = 'made-cms:media-thumbnails';

    protected $description = 'Make the thumbnails of the photos in the Made CMS media library that have none';

    public function handle(): int
    {
        $made = MakeMissingThumbnails::run();

        $this->components->info((string) __('made-cms-package::console.media_thumbnails.made', [
            'made' => trans_choice('made-cms-package::console.media_thumbnails.count', $made->made),
            'had' => trans_choice('made-cms-package::console.media_thumbnails.had', $made->had),
        ]));

        foreach ($made->failed as $failure) {
            $this->components->warn((string) __('made-cms-package::console.media_thumbnails.'.$failure['reason'], [
                'name' => $failure['name'],
                'id' => $failure['id'],
            ]));
        }

        return $made->failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
