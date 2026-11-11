<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MediaFolderCount;
use Made\CmsPackage\Content\Data\MediaFolders;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

final class ListMediaFolders
{
    use AsAction;

    /**
     * Every folder of the media library from a to z with the number of its files, and the number of all files
     * ("Alle bestanden"), also those in no folder. With a kind only the files of that kind count: the picker of an
     * image counts the images, an empty folder too.
     */
    public function handle(?MediaKind $kind = null): MediaFolders
    {
        $files = Media::query();

        if ($kind !== null) {
            $files->ofKind($kind);
        }

        $counts = $files->clone()->countPerFolder();
        $folders = [];

        foreach (MediaFolder::query()->alphabetical()->get() as $folder) {
            $folders[] = new MediaFolderCount($folder->id, $folder->name, $counts[$folder->id] ?? 0);
        }

        return new MediaFolders($folders, $files->count());
    }
}
