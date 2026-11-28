<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Content\Data\SitemapSearch;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The address of the screen of the sitemap: the route group it shows (groep). A screen never refuses its address: a
 * group it does not know shows the first one.
 */
final class ShowSitemapScreenRequest extends LauncherRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    public function toData(): SitemapSearch
    {
        $group = $this->query('groep');

        return new SitemapSearch(is_string($group) ? trim($group, '/') : null);
    }
}
