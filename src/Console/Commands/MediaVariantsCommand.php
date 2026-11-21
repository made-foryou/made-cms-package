<?php

declare(strict_types=1);

namespace Made\CmsPackage\Console\Commands;

use Illuminate\Console\Command;
use Made\CmsPackage\Content\Actions\MakeMissingVariants;

/**
 * Makes the narrower sizes of the photos of the media library that have none: once on a site that had photos before
 * the library made them.
 */
final class MediaVariantsCommand extends Command
{
    protected $signature = 'made-cms:media-variants';

    protected $description = 'Make the narrower sizes of the photos in the Made CMS media library that have none';

    public function handle(): int
    {
        $made = MakeMissingVariants::run();

        $this->components->info((string) __('made-cms-package::console.media_variants.made', [
            'made' => trans_choice('made-cms-package::console.media_variants.count', $made->made),
            'had' => trans_choice('made-cms-package::console.media_variants.had', $made->had),
        ]));

        foreach ($made->failed as $failure) {
            $this->components->warn((string) __('made-cms-package::console.media_variants.'.$failure['reason'], [
                'name' => $failure['name'],
                'id' => $failure['id'],
            ]));
        }

        return $made->failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
