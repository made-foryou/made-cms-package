<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Exceptions\PagePathTooLong;
use Made\CmsPackage\Content\Models\Page;

final class RewritePagePaths
{
    use AsAction;

    /**
     * Gives a page a new address and the pages under it the addresses that follow from it.
     *
     * It works out every address before it writes one, so an address that does not fit writes nothing. The caller
     * checks that the new address is free and runs this in its transaction. The tree is consistent (every address is
     * that of its parent plus its slug), so no address in between clashes with another page.
     *
     * @throws PagePathTooLong when the address of the page or of a page under it is longer than PagePath::MAX_LENGTH
     */
    public function handle(Page $page, PagePath $path): void
    {
        foreach ($this->addresses($page, $path) as [$each, $address]) {
            $each->route()->updateOrCreate([], ['path' => $address->value]);
            $each->unsetRelation('route');
        }
    }

    /**
     * The page and every page under it with its new address, each parent before the pages under it.
     *
     * @return list<array{Page, PagePath}>
     *
     * @throws PagePathTooLong
     */
    private function addresses(Page $page, PagePath $path): array
    {
        if (! $path->fits()) {
            throw PagePathTooLong::at($path);
        }

        $addresses = [[$page, $path]];

        foreach (Page::query()->childrenOf($page)->inMenuOrder()->get() as $child) {
            array_push($addresses, ...$this->addresses($child, $path->child($child->slug)));
        }

        return $addresses;
    }
}
