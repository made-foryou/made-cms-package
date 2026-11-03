<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Models\Page;

final class RenumberPages
{
    use AsAction;

    /**
     * Gives siblings the places 0, 1, 2… in the given order. A sibling that keeps its place is not touched, and a
     * new place is no edit of the page, so its time of the last change stays.
     *
     * @param  list<int>  $ids  the siblings in their new order
     */
    public function handle(array $ids): void
    {
        foreach ($ids as $position => $id) {
            Page::query()->whereKey($id)->where('position', '!=', $position)->toBase()->update(['position' => $position]);
        }
    }
}
