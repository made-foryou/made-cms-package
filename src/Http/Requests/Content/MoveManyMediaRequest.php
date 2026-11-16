<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The files of the media library an editor chose, and the folder they go into, or null for "Alle bestanden". Whether
 * the folder and the files exist is checked by MoveMedia.
 */
final class MoveManyMediaRequest extends LauncherRequest
{
    /**
     * The most files one request moves.
     */
    public const int MAX_FILES = 200;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_FILES],
            'files.*' => ['required', 'integer', 'min:1', 'distinct'],
            'folder' => ['present', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files' => $this->error('files'),
            'files.*' => $this->error('files'),
            'folder.*' => $this->error('folder'),
        ];
    }

    /**
     * @return list<int>
     */
    public function mediaIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->input('files')));
    }

    public function folderId(): ?int
    {
        return $this->filled('folder') ? $this->integer('folder') : null;
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::media.errors.'.$key);
    }
}
