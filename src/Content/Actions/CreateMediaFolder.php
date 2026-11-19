<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
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
     * another folder has. The insert runs in a transaction of its own, a savepoint inside a transaction of the host:
     * PostgreSQL aborts the whole transaction on a refused statement, but only up to its savepoint.
     *
     * @throws ValidationException keyed by "name"
     */
    public function handle(string $name): MediaFolder
    {
        $name = CheckMediaFolderName::run($name);

        try {
            $folder = DB::transaction(static fn (): MediaFolder => MediaFolder::query()->create(['name' => $name]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => (string) __('made-cms-package::media.errors.folder_taken', ['name' => $name]),
            ]);
        }

        MediaFolderCreated::dispatch($folder);

        return $folder;
    }
}
