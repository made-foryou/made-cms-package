<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Illuminate\Validation\Rule;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The folders of the media library, with the files of one kind counted when the list shows one kind (the picker of an
 * image counts images), only the images without an alt text when it shows those, and only the files a search finds.
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
            'without_alt' => ['sometimes', 'boolean'],
            'q' => ['nullable', 'string', 'max:255'],
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

    public function withoutAlt(): bool
    {
        return $this->boolean('without_alt');
    }

    public function search(): string
    {
        return $this->string('q')->trim()->toString();
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::media.errors.'.$key);
    }
}
