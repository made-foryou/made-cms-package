<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Illuminate\Validation\Rule;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The folders of the media library, with the files of one kind counted when the list shows one kind (the picker of an
 * image counts images).
 */
final class ListMediaFoldersRequest extends LauncherRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['nullable', Rule::enum(MediaKind::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['*' => $this->error('filter')];
    }

    public function kind(): ?MediaKind
    {
        return $this->enum('kind', MediaKind::class);
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::media.errors.'.$key);
    }
}
