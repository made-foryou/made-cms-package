<?php

declare(strict_types=1);

namespace Made\CmsPackage\Console\Commands;

use Illuminate\Console\Command;
use Made\CmsPackage\Content\Actions\PruneAddressHits;
use Made\CmsPackage\Content\Actions\PrunePages;
use Made\CmsPackage\Content\Actions\PrunePageVersions;

/**
 * Removes deleted pages for good once they can no longer be restored, the versions of a page past the number it keeps,
 * and the counts of the addresses older than the days they are kept; the package runs it every day.
 */
final class PrunePagesCommand extends Command
{
    protected $signature = 'made-cms:prune';

    protected $description = 'Remove Made CMS pages that were deleted longer ago than they are kept, older versions of pages and old counts of addresses';

    public function handle(): int
    {
        $pruned = PrunePages::run();

        $this->components->info(trans_choice('made-cms-package::console.prune.pruned', $pruned));

        $versions = PrunePageVersions::run();

        $this->components->info(trans_choice('made-cms-package::console.prune.versions', $versions));

        $addresses = PruneAddressHits::run();

        $this->components->info(trans_choice('made-cms-package::console.prune.address_hits', $addresses->hits));
        $this->components->info(trans_choice('made-cms-package::console.prune.not_found_paths', $addresses->paths));

        return self::SUCCESS;
    }
}
