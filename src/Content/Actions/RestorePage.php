<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Events\PageRestored;
use Made\CmsPackage\Content\Exceptions\PageNotDeleted;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
use Made\CmsPackage\Content\Exceptions\PagePathTooLong;
use Made\CmsPackage\Content\Exceptions\ParentPageDeleted;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Route;

final class RestorePage
{
    use AsAction;

    /**
     * Brings a deleted page back at the address of its parent and its slug, last among its siblings. The pages under
     * it stay deleted until they are restored themselves.
     *
     * @throws PageNotDeleted
     * @throws ParentPageDeleted
     * @throws PagePathTooLong
     * @throws PagePathTaken
     */
    public function handle(Page $page): Page
    {
        if (! $page->trashed()) {
            throw PageNotDeleted::for($page);
        }

        $parent = $page->parent()->withTrashed()->with('route')->first();

        if ($parent?->trashed() === true) {
            throw ParentPageDeleted::for($parent);
        }

        $path = PagePath::fromString((string) $parent?->route?->path)->child($page->slug);

        if (! $path->fits()) {
            throw PagePathTooLong::at($path);
        }

        try {
            DB::transaction(function () use ($page, $parent, $path): void {
                // Another request may have restored it since it was read: the row lock makes the second one wait.
                if (! Page::query()->withTrashed()->whereKey($page->id)->lockForUpdate()->firstOrFail()->trashed()) {
                    throw PageNotDeleted::for($page);
                }

                if (Route::query()->atPath($path)->exists()) {
                    throw PagePathTaken::at($path);
                }

                $last = Page::query()->childrenOf($parent)->max('position');

                $page->position = is_numeric($last) ? (int) $last + 1 : 0;
                $page->restore();
                $page->route()->create(['path' => $path->value]);
            });
        } catch (UniqueConstraintViolationException) {
            // Another request stored a page at the address after the check: the unique index of cms_routes is the
            // last guard, and the only one this transaction writes. Caught outside it, so it has been rolled back.
            throw PagePathTaken::at($path);
        }

        PageRestored::dispatch($page->load('route'), $path);

        return $page;
    }
}
