<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Events\NotFoundPathIgnored;
use Made\CmsPackage\Content\Models\NotFoundPath;

final class IgnoreNotFoundPath
{
    use AsAction;

    /**
     * Takes a dead door out of the list, also when people ask for it again. Its visits are still counted, so
     * restoring it brings it back with them. An address that is ignored keeps the moment it was first ignored.
     */
    public function handle(NotFoundPath $path): NotFoundPath
    {
        // Only the request that ignores it first writes, so two at the same time tell it once.
        $ignored = NotFoundPath::query()->whereKey($path->id)->notIgnored()->update(['ignored_at' => now()]) === 1;

        $path->refresh();

        if ($ignored) {
            NotFoundPathIgnored::dispatch($path);
        }

        return $path;
    }
}
