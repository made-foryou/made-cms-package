<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Models\MediaFolder;

final class CheckMediaFolderName
{
    use AsAction;

    /**
     * The longest name a folder chip still shows in full.
     */
    public const int MAX_LENGTH = 60;

    /**
     * Gives the name a folder of the library is stored with: trimmed, with single spaces. A name is never empty, never
     * longer than MAX_LENGTH characters, and no other folder has it, whatever its capitals ("teams" is "Teams"); the
     * folder that is renamed ($except) may keep it, in other capitals too.
     *
     * The unique index on the name is the last guard against a folder created with it at the same moment.
     *
     * @throws ValidationException keyed by "name"
     */
    public function handle(string $name, ?MediaFolder $except = null): string
    {
        $name = Str::squish($name);

        if ($name === '') {
            throw self::refuse('folder_name');
        }

        if (mb_strlen($name) > self::MAX_LENGTH) {
            throw self::refuse('folder_name_long', ['max' => self::MAX_LENGTH]);
        }

        $taken = MediaFolder::query()
            ->named($name)
            ->when($except !== null, static fn ($query) => $query->whereKeyNot($except?->id))
            ->first();

        if ($taken !== null) {
            throw self::refuse('folder_taken', ['name' => $taken->name]);
        }

        return $name;
    }

    /**
     * @param  array<string, int|string>  $replace
     */
    private static function refuse(string $error, array $replace = []): ValidationException
    {
        return ValidationException::withMessages([
            'name' => (string) __('made-cms-package::media.errors.'.$error, $replace),
        ]);
    }
}
