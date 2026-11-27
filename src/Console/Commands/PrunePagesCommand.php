<?php

declare(strict_types=1);

namespace Made\CmsPackage\Console\Commands;

use Illuminate\Console\Command;
use Made\CmsPackage\Content\Actions\PrunePages;
use Made\CmsPackage\Content\Actions\PrunePageVersions;

/**
 * Removes deleted pages for good once they can no longer be restored, and the versions of a page past the number it
 * keeps; the package runs it every day.
 */
final class PrunePagesCommand extends Command
{
    protected $signature = 'made-cms:prune';

    protected $description = 'Remove Made CMS pages that were deleted longer ago than they are kept, and older versions of pages';

    public function handle(): int
    {
        $pruned = PrunePages::run();

        $this->components->info(trans_choice('made-cms-package::console.prune.pruned', $pruned));

        $versions = PrunePageVersions::run();

        $this->components->info(trans_choice('made-cms-package::console.prune.versions', $versions));

        return self::SUCCESS;
    }
}
