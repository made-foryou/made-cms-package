<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Events\MediaFolderDeleted;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

final class DeleteMediaFolder
{
    use AsAction;

    /**
     * Takes an empty folder out of the library. A folder that holds files stays, and the refusal says how many, so the
     * editor knows what to move first; nothing is lost, so an empty folder goes without asking. A folder that is gone
     * already is no refusal.
     *
     * It counts the files of the folder as it is now, with its row locked: a file moved or uploaded into it waits for
     * the shared lock MoveMedia and UploadMedia take on it, and the foreign key of the files is the last guard.
     *
     * @throws ValidationException keyed by "folder"
     */
    public function handle(MediaFolder $folder): void
    {
        DB::transaction(static function () use ($folder): void {
            $stored = MediaFolder::query()->whereKey($folder->id)->lockForUpdate()->first();

            if ($stored === null) {
                return;
            }

            $files = Media::query()->inFolder($stored)->count();

            if ($files > 0) {
                throw ValidationException::withMessages([
                    'folder' => trans_choice('made-cms-package::media.errors.folder_not_empty', $files, ['name' => $stored->name, 'count' => $files]),
                ]);
            }

            $stored->delete();

            MediaFolderDeleted::dispatch($stored);
        });
    }
}
