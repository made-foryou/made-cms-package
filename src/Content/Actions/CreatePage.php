<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\NewPage;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\PageSlug;
use Made\CmsPackage\Content\Events\PageCreated;
use Made\CmsPackage\Content\Exceptions\InvalidPageSlug;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
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
     * @throws PagePathTaken
     */
    public function handle(NewPage $new): Page
    {
        $parent = FindParentPage::run($new->parentId);
        $slug = PageSlug::fromString(trim((string) $new->slug) !== '' ? (string) $new->slug : $new->title);
        $path = PagePath::fromString((string) $parent?->route?->path)->child($slug->value);

        if (Route::query()->atPath($path)->exists()) {
            throw PagePathTaken::at($path);
        }

        $page = DB::transaction(function () use ($new, $parent, $slug, $path): Page {
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

        PageCreated::dispatch($page);

        return $page;
    }
}
