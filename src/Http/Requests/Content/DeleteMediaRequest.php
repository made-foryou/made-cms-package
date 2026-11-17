<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The files of the media library an editor wants deleted: the chosen file, or a selection. Whether they exist and
 * whether a page uses them is checked by DeleteMedia, which deletes only what no page uses.
 */
final class DeleteMediaRequest extends LauncherRequest
{
    /**
     * The most files one request deletes.
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files' => $this->error('files_delete'),
            'files.*' => $this->error('files_delete'),
        ];
    }

    /**
     * @return list<int>
     */
    public function mediaIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->input('files')));
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::media.errors.'.$key);
    }
}
