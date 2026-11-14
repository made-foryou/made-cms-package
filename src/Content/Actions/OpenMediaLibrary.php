<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MediaFilter;
use Made\CmsPackage\Content\Data\MediaLibrary;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;

final class OpenMediaLibrary
{
    use AsAction;

    /**
     * The media library as its screen opens with a filter: the first page of its files and the folders, counted as
     * the filter lists them. A folder that is gone opens "Alle bestanden" instead, so an old address still opens.
     *
     * @throws MediaDiskNotUsable when there are files and the media disk gives no url a visitor can open
     */
    public function handle(MediaFilter $filter): MediaLibrary
    {
        $folder = FindMediaFolder::run($filter->folderId);
        $filter = new MediaFilter(
            folderId: $folder?->id,
            search: $filter->search,
            kind: $filter->kind,
            withoutAlt: $filter->withoutAlt,
            sort: $filter->sort,
        );

        return new MediaLibrary(
            filter: $filter,
            folder: $folder,
            list: ListMedia::run($filter),
            folders: ListMediaFolders::run($filter->kind, $filter->withoutAlt, $filter->search),
        );
    }
}
