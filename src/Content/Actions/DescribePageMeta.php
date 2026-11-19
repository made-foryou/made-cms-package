<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageMeta;
use Made\CmsPackage\Content\Data\ServedAddress;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Support\Site;

final class DescribePageMeta
{
    use AsAction;

    public function __construct(
        private readonly ServedAddress $served,
    ) {}

    /**
     * What the head of a page tells Google. The canonical address comes from the url of the site, never from the Host of
     * the request, so every form of the address (with or without www, http) leads Google to the same one. The page the
     * request serves keeps the prefix of its route group in it ("/nl/over-ons").
     */
    public function handle(Page $page): PageMeta
    {
        $path = $this->served->pageId === $page->id ? $this->served->path : $page->route?->path;

        return new PageMeta(
            title: $page->meta_title ?? MakeFallbackTitle::run($page),
            description: $page->meta_description,
            canonical: $path === null ? null : Site::url($path),
            indexable: $page->indexable,
        );
    }
}
