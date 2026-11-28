<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Content\Data\MetaSearch;
use Made\CmsPackage\Content\Enums\MetaFilter;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The address of the Meta-overzicht: the filter of the list (filter), the page open beside it (page) and the points
 * this round put in order (done). A screen never refuses its address: what it does not know is left out.
 */
final class ShowMetaTagsRequest extends LauncherRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    public function toData(): MetaSearch
    {
        $page = filter_var($this->text('page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $done = filter_var($this->text('done'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => MetaSearch::MAX_DONE]]);

        return new MetaSearch(
            filter: MetaFilter::tryFrom($this->text('filter')),
            pageId: $page === false ? null : $page,
            done: $done === false ? 0 : $done,
        );
    }

    private function text(string $key): string
    {
        $value = $this->query($key);

        return is_string($value) ? $value : '';
    }
}
