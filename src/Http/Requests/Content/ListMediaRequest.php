<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Illuminate\Validation\Rule;
use Made\CmsPackage\Content\Data\MediaFilter;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Enums\MediaSort;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * What a list of the media library shows, from the query of the picker (and later the library): the folder, the
 * search, the kind, only the images without an alt text, the order and the page.
 */
final class ListMediaRequest extends LauncherRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'folder' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:255'],
            'kind' => ['nullable', Rule::enum(MediaKind::class)],
            'without_alt' => ['sometimes', 'boolean'],
            'sort' => ['nullable', Rule::enum(MediaSort::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'folder.*' => $this->error('folder'),
            '*' => $this->error('filter'),
        ];
    }

    public function toData(): MediaFilter
    {
        return new MediaFilter(
            folderId: $this->filled('folder') ? $this->integer('folder') : null,
            search: $this->string('q')->trim()->toString(),
            kind: $this->enum('kind', MediaKind::class),
            withoutAlt: $this->boolean('without_alt'),
            sort: $this->enum('sort', MediaSort::class) ?? MediaSort::Newest,
            page: $this->filled('page') ? $this->integer('page') : 1,
        );
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::media.errors.'.$key);
    }
}
