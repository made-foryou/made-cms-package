<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Content\Data\PageSearch;
use Made\CmsPackage\Content\Enums\PageFilter;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The address of the pages screen: what it searches (q) and which filter it shows (filter). A screen never refuses its
 * address: what it does not know is left out.
 */
final class ShowPagesRequest extends LauncherRequest
{
    private const int MAX_SEARCH = 255;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    public function toData(): PageSearch
    {
        return new PageSearch(
            text: mb_substr(trim($this->text('q')), 0, self::MAX_SEARCH),
            filter: PageFilter::tryFrom($this->text('filter')) ?? PageFilter::All,
        );
    }

    private function text(string $key): string
    {
        $value = $this->query($key);

        return is_string($value) ? $value : '';
    }
}
