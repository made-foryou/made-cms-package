<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Content\Data\MediaFilter;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Enums\MediaSort;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * What the media library opens with, from its address: the folder, a kind or the images without an alt text (one of
 * them, as its filters are), the search, the order and the chosen file. A screen never refuses its address: what it
 * does not know is left out.
 */
final class ShowMediaLibraryRequest extends LauncherRequest
{
    private const int MAX_SEARCH = 255;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    public function toData(): MediaFilter
    {
        $withoutAlt = $this->boolean('without_alt');

        return new MediaFilter(
            folderId: $this->id('folder'),
            search: mb_substr(trim($this->text('q')), 0, self::MAX_SEARCH),
            kind: $withoutAlt ? null : MediaKind::tryFrom($this->text('kind')),
            withoutAlt: $withoutAlt,
            sort: MediaSort::tryFrom($this->text('sort')) ?? MediaSort::Newest,
        );
    }

    /**
     * The file the library opens with chosen; null for none.
     */
    public function chosen(): ?int
    {
        return $this->id('file');
    }

    private function text(string $key): string
    {
        $value = $this->query($key);

        return is_string($value) ? $value : '';
    }

    private function id(string $key): ?int
    {
        $id = filter_var($this->text($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }
}
