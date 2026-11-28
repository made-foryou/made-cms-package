<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Events\NotFoundPathRestored;
use Made\CmsPackage\Content\Models\NotFoundPath;

final class RestoreNotFoundPath
{
    use AsAction;

    /**
     * Puts an ignored address back in the list of dead doors, with the visits it got while it was ignored.
     */
    public function handle(NotFoundPath $path): NotFoundPath
    {
        $restored = NotFoundPath::query()->whereKey($path->id)->ignored()->update(['ignored_at' => null]) === 1;

        $path->refresh();

        if ($restored) {
            NotFoundPathRestored::dispatch($path);
        }

        return $path;
    }
}
