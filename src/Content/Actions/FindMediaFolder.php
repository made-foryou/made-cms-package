<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Models\MediaFolder;

final class FindMediaFolder
{
    use AsAction;

    /**
     * The folder of the media library with this id; null without an id or once the folder is gone.
     */
    public function handle(?int $id): ?MediaFolder
    {
        return $id === null ? null : MediaFolder::query()->find($id);
    }
}
