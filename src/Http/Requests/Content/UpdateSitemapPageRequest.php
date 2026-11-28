<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Content\Data\PageMetaChanges;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Cms\SitemapLinks;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The switch "in sitemap" of a page on the screen of the sitemap, with the route group the screen showed (groep), so
 * the screen comes back at that page in that group.
 */
final class UpdateSitemapPageRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'in_sitemap' => ['required', 'boolean'],
        ];
    }

    public function toData(): PageMetaChanges
    {
        return PageMetaChanges::sitemap($this->boolean('in_sitemap'), $this->editorId());
    }

    /**
     * The route group the screen showed, "" at the root.
     */
    public function group(): string
    {
        $group = $this->input('groep');

        return is_string($group) ? trim($group, '/') : '';
    }

    protected function getRedirectUrl(): string
    {
        $page = $this->route('page');

        return SitemapLinks::screen($this->group(), $page instanceof Page ? SitemapLinks::row($page->id) : null);
    }
}
