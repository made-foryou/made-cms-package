<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Data\PageSearch;
use Made\CmsPackage\Content\Data\PagesOverview;
use Made\CmsPackage\Content\Enums\PageFilter;

final class OpenPagesOverview
{
    use AsAction;

    /**
     * The pages screen: every page, what it asks for attention, the pages of every filter over the whole site and the
     * part of the tree the search shows. A page matches when it is in the filter and its name or address holds the
     * text, whatever the capitals; the tree keeps the pages above it, so its place stays clear.
     */
    public function handle(PageSearch $search): PagesOverview
    {
        $tree = ListPages::run();
        $attention = ListAttentionByPage::run();
        $pages = $tree->flatten();

        $filtered = [];

        foreach (PageFilter::cases() as $filter) {
            $filtered[$filter->value] = array_values(array_filter($pages, static fn (PageNode $node): bool => $filter->matches($node, $attention)));
        }

        $matches = array_values(array_map(
            static fn (PageNode $node): int => $node->id,
            array_filter($filtered[$search->filter->value], fn (PageNode $node): bool => $this->holds($node, $search->text)),
        ));

        return new PagesOverview(
            tree: $tree,
            shown: $search->isFiltered() ? $tree->keeping(static fn (PageNode $node): bool => in_array($node->id, $matches, true)) : $tree,
            matches: $matches,
            search: $search,
            attention: $attention,
            filtered: $filtered,
            inSiteMenu: count($tree->siteMenu()->flatten()),
        );
    }

    private function holds(PageNode $node, string $text): bool
    {
        return $text === ''
            || mb_stripos($node->title, $text) !== false
            || ($node->path !== null && mb_stripos($node->path->value, $text) !== false);
    }
}
