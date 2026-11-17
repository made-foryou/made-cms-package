<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Data\MediaFilter;
use Made\CmsPackage\Content\Data\MediaList;
use Made\CmsPackage\Content\Enums\MediaSort;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Exceptions\MediaFolderNotFound;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;

final class ListMedia
{
    use AsAction;

    /** The number of files on one page of a list. */
    public const int PER_PAGE = 40;

    /**
     * One page of the files of the media library the filter asks for, each with the url a visitor opens it at: the
     * files of a folder or all of them, whose name or alt text holds the search, of a kind, only the images without an
     * alt text, newest first or by name.
     *
     * @throws MediaFolderNotFound when the folder of the filter is gone
     * @throws MediaDiskNotUsable when there is a file to list and the media disk gives no url a visitor can open
     */
    public function handle(MediaFilter $filter): MediaList
    {
        $query = Media::query()->search($filter->search);

        if ($filter->folderId !== null) {
            $query->inFolder(FindMediaFolder::run($filter->folderId) ?? throw MediaFolderNotFound::id($filter->folderId));
        }

        if ($filter->kind !== null) {
            $query->ofKind($filter->kind);
        }

        if ($filter->withoutAlt) {
            $query->withoutAlt();
        }

        $page = max(1, $filter->page);
        $total = $query->clone()->count();
        $ids = array_values(array_map(intval(...), ($filter->sort === MediaSort::Name ? $query->byName() : $query->newestFirst())
            ->forPage($page, self::PER_PAGE)
            ->pluck('id')
            ->all()));
        // FindMedia gives the url of a file; it finds them by id, so they go back in the order of the list.
        $found = FindMedia::run($ids);
        $files = array_values(array_filter(
            array_map(static fn (int $id): ?MediaFile => $found->find($id), $ids),
            static fn (?MediaFile $file): bool => $file !== null,
        ));

        // A file a page uses cannot be deleted: the list says so before the editor asks (DeleteMedia checks again).
        $used = $ids === [] ? [] : array_values(array_map(
            intval(...),
            MediaUsage::query()->ofMedia($ids)->distinct()->orderBy('media_id')->pluck('media_id')->all(),
        ));

        return new MediaList($files, $page, $total, $page * self::PER_PAGE < $total, $used);
    }
}
