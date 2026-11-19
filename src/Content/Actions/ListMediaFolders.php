<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MediaCounts;
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
     * image counts the images, an empty folder too; with $withoutAlt only the images without an alt text count, as the
     * library lists them under "zonder alt-tekst"; with a search only the files it finds. Several kinds ($kinds, the
     * picker of a file field: documents and videos) win from $kind. The numbers per kind and of the images without an
     * alt text are those of the whole library, whatever the kind: the filters of the library show them.
     *
     * @param  list<MediaKind>  $kinds
     */
    public function handle(?MediaKind $kind = null, bool $withoutAlt = false, string $search = '', array $kinds = []): MediaFolders
    {
        $files = Media::query()->search($search);

        if ($kinds !== []) {
            $files->ofKinds($kinds);
        } elseif ($kind !== null) {
            $files->ofKind($kind);
        }

        if ($withoutAlt) {
            $files->withoutAlt();
        }

        $counts = $files->clone()->countPerFolder();
        $folders = [];

        foreach (MediaFolder::query()->alphabetical()->get() as $folder) {
            $folders[] = new MediaFolderCount($folder->id, $folder->name, $counts[$folder->id] ?? 0);
        }

        $kinds = Media::query()->countPerKind();
        $counts = new MediaCounts(
            all: array_sum($kinds),
            image: $kinds[MediaKind::Image->value],
            document: $kinds[MediaKind::Document->value],
            video: $kinds[MediaKind::Video->value],
            withoutAlt: Media::query()->withoutAlt()->count(),
        );

        return new MediaFolders($folders, $files->count(), $counts);
    }
}
