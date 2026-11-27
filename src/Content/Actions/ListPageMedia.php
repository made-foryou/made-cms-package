<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Data\PageMedia;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Models\Page;

final class ListPageMedia
{
    use AsAction;

    /**
     * The files on the working copy of a page, from the places of its strips (cms_media_usages): each once, in the order
     * the page shows them, a hidden strip and a strip whose type left the catalogue too, and which of them need an alt
     * text. Its share image lies outside its strips and is left out. A media disk a visitor cannot open is reported
     * and shows no files, so the screen of the page keeps working.
     */
    public function handle(Page $page): PageMedia
    {
        $ids = ListWorkingCopyMedia::run($page, $page->mediaUsages);

        if ($ids === []) {
            return new PageMedia;
        }

        try {
            $found = FindMedia::run($ids);
        } catch (MediaDiskNotUsable $unusable) {
            report($unusable);

            return new PageMedia(available: false);
        }

        $files = array_values(array_filter(array_map($found->find(...), $ids), static fn (?MediaFile $file): bool => $file !== null));
        $withoutAlt = ListWorkingCopyMedia::run($page, $page->usagesWithoutAlt);

        return new PageMedia(
            files: $files,
            withoutAlt: array_values(array_filter($ids, static fn (int $id): bool => in_array($id, $withoutAlt, true))),
        );
    }
}
