<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The name an editor typed for a folder of the media library. Trimming it and whether it can be taken are rules of
 * CheckMediaFolderName.
 */
final class RenameMediaFolderRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.*' => $this->error('folder_name')];
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::media.errors.'.$key);
    }
}
