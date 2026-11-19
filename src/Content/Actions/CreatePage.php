<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\NewPage;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\PageSlug;
use Made\CmsPackage\Content\Events\PageCreated;
use Made\CmsPackage\Content\Exceptions\InvalidPageSlug;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
use Made\CmsPackage\Content\Exceptions\PagePathTooLong;
use Made\CmsPackage\Content\Exceptions\PageSlugTooLong;
use Made\CmsPackage\Content\Exceptions\ParentPageDeleted;
use Made\CmsPackage\Content\Exceptions\ParentPageNotFound;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Route;

final class CreatePage
{
    use AsAction;

    /**
     * Creates an empty draft with its address, last among the pages with the same parent.
     *
     * @throws ParentPageNotFound
     * @throws ParentPageDeleted
     * @throws InvalidPageSlug
     * @throws PageSlugTooLong
     * @throws PagePathTooLong
     * @throws PagePathTaken
     */
    public function handle(NewPage $new): Page
    {
        $parent = FindParentPage::run($new->parentId);
        $slug = PageSlug::fromString(trim((string) $new->slug) !== '' ? (string) $new->slug : $new->title);
        $path = PagePath::fromString((string) $parent?->route?->path)->child($slug->value);

        if (! $path->fits()) {
            throw PagePathTooLong::at($path);
        }

        try {
            $page = DB::transaction(function () use ($new, $parent, $slug, $path): Page {
                if (Route::query()->atPath($path)->exists()) {
                    throw PagePathTaken::at($path);
                }

                $last = Page::query()->childrenOf($parent)->max('position');

                $page = Page::query()->create([
                    'parent_id' => $parent?->id,
                    'title' => trim($new->title),
                    'slug' => $slug->value,
                    'position' => is_numeric($last) ? (int) $last + 1 : 0,
                    'in_menu' => $new->inMenu,
                ]);
                $page->route()->create(['path' => $path->value]);

                return $page->load('route');
            });
        } catch (UniqueConstraintViolationException) {
            // Another request stored a page at the address after the check: the unique index of cms_routes is the
            // last guard, and the only one this transaction writes. Caught outside it, so it has been rolled back.
            throw PagePathTaken::at($path);
        }

        PageCreated::dispatch($page);

        return $page;
    }
}
