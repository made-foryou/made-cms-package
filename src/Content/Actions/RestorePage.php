<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

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

        if (Route::query()->atPath($path)->exists()) {
            throw PagePathTaken::at($path);
        }

        DB::transaction(function () use ($page, $parent, $path): void {
            $last = Page::query()->childrenOf($parent)->max('position');

            $page->position = is_numeric($last) ? (int) $last + 1 : 0;
            $page->restore();
            $page->route()->create(['path' => $path->value]);
        });

        PageRestored::dispatch($page->load('route'), $path);

        return $page;
    }
}
