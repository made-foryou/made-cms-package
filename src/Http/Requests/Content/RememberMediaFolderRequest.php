<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The folder an editor opened in the picker, or null for "Alle bestanden". Whether it still exists is checked when
 * the next edit shell reads it back.
 */
final class RememberMediaFolderRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'folder' => ['present', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['folder.*' => $this->error('folder')];
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
