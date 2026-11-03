<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Exceptions\ParentPageDeleted;
use Made\CmsPackage\Content\Exceptions\ParentPageNotFound;
use Made\CmsPackage\Content\Models\Page;

final class FindParentPage
{
    use AsAction;

    /**
     * The page another page goes under; null for the top of the site.
     *
     * @throws ParentPageNotFound
     * @throws ParentPageDeleted
     */
    public function handle(?int $id): ?Page
    {
        if ($id === null) {
            return null;
        }

        $parent = Page::query()->withTrashed()->with('route')->find($id) ?? throw ParentPageNotFound::id($id);

        if ($parent->trashed()) {
            throw ParentPageDeleted::for($parent);
        }

        return $parent;
    }
}
