<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Events\MediaFolderCreated;
use Made\CmsPackage\Content\Models\MediaFolder;

final class CreateMediaFolder
{
    use AsAction;

    /**
     * Adds a folder to the media library, one level deep, with the name CheckMediaFolderName gives. A folder created
     * with the same name at the same moment makes the unique index refuse it, which is the same refusal as a name
     * another folder has.
     *
     * @throws ValidationException keyed by "name"
     */
    public function handle(string $name): MediaFolder
    {
        $name = CheckMediaFolderName::run($name);

        try {
            $folder = MediaFolder::query()->create(['name' => $name]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => (string) __('made-cms-package::media.errors.folder_taken', ['name' => $name]),
            ]);
        }

        MediaFolderCreated::dispatch($folder);

        return $folder;
    }
}
