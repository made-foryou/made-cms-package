<?php

declare(strict_types=1);

namespace Made\CmsPackage\Console\Commands;

use Illuminate\Console\Command;
use Made\CmsPackage\Content\Actions\PublishScheduledPages;

/**
 * Puts the drafts live whose planned moment came; the package runs it every minute.
 */
final class PublishScheduledPagesCommand extends Command
{
    protected $signature = 'made-cms:publish-scheduled';

    protected $description = 'Publish the Made CMS pages whose planned moment came';

    public function handle(): int
    {
        $publications = PublishScheduledPages::run();

        $this->components->info(trans_choice('made-cms-package::console.publish.published', $publications->published));
        $this->components->info(trans_choice('made-cms-package::console.publish.refused', $publications->refused));

        return self::SUCCESS;
    }
}
