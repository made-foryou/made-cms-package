<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Events\MediaFolderRenamed;
use Made\CmsPackage\Content\Exceptions\MediaFolderNotFound;
use Made\CmsPackage\Content\Models\MediaFolder;

final class RenameMediaFolder
{
    use AsAction;

    /**
     * Gives a folder of the library another name, the one CheckMediaFolderName gives; its files stay in it, and their
     * addresses stay too, because a folder of the library is no folder on the disk. Other capitals are another name.
     *
     * It renames the folder as it is now, read again with its row locked, never the model it is handed.
     *
     * @throws ValidationException keyed by "name"
     * @throws MediaFolderNotFound
     */
    public function handle(MediaFolder $folder, string $name): MediaFolder
    {
        return DB::transaction(static function () use ($folder, $name): MediaFolder {
            $stored = MediaFolder::query()->whereKey($folder->id)->lockForUpdate()->first()
                ?? throw MediaFolderNotFound::id($folder->id);

            $name = CheckMediaFolderName::run($name, $stored);

            if ($stored->name === $name) {
                return $stored;
            }

            try {
                $stored->update(['name' => $name]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => (string) __('made-cms-package::media.errors.folder_taken', ['name' => $name]),
                ]);
            }

            MediaFolderRenamed::dispatch($stored);

            return $stored;
        });
    }
}
