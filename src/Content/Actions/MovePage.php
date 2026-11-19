<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Events\PageMoved;
use Made\CmsPackage\Content\Exceptions\CircularPageMove;
use Made\CmsPackage\Content\Exceptions\HomePageProtected;
use Made\CmsPackage\Content\Exceptions\InvalidPagePosition;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
use Made\CmsPackage\Content\Exceptions\PagePathTooLong;
use Made\CmsPackage\Content\Exceptions\ParentPageDeleted;
use Made\CmsPackage\Content\Exceptions\ParentPageNotFound;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Route;

final class MovePage
{
    use AsAction;

    /**
     * Puts a page under a parent at a place among its siblings; the pages under it move along and every address
     * follows. The siblings it leaves and joins close up.
     *
     * @param  int|null  $parentId  null for the top of the site
     * @param  int|null  $position  0 for the first place; null for the last
     *
     * @throws ParentPageNotFound
     * @throws ParentPageDeleted
     * @throws HomePageProtected
     * @throws CircularPageMove
     * @throws InvalidPagePosition
     * @throws PagePathTaken
     * @throws PagePathTooLong when the address of the page or of a page under it gets too long
     */
    public function handle(Page $page, ?int $parentId, ?int $position = null): Page
    {
        $page->loadMissing('route');
        $parent = FindParentPage::run($parentId);
        CheckPageParent::run($page, $parent);

        $siblings = Page::query()->childrenOf($parent)->inMenuOrder()->get()->map(static fn (Page $sibling): int => $sibling->id)->all();
        $others = array_values(array_filter($siblings, static fn (int $id): bool => $id !== $page->id));
        $position ??= count($others);

        if ($position < 0 || $position > count($others)) {
            throw InvalidPagePosition::for($page, $position, count($others));
        }

        $sameParent = $page->parent_id === $parent?->id;

        if ($sameParent && array_search($page->id, $siblings, true) === $position) {
            return $page;
        }

        $from = PagePath::fromString((string) $page->route?->path);
        $to = $sameParent ? $from : PagePath::fromString((string) $parent?->route?->path)->child($page->slug);

        try {
            DB::transaction(function () use ($page, $parent, $position, $others, $sameParent, $from, $to): void {
                if ($to != $from && Route::query()->atPath($to)->exists()) {
                    throw PagePathTaken::at($to);
                }

                $leaving = $sameParent ? [] : Page::query()->childrenOf($page->parent)->inMenuOrder()->get()->map(static fn (Page $sibling): int => $sibling->id)->all();

                $page->parent_id = $parent?->id;
                $page->position = $position;
                $page->save();

                array_splice($others, $position, 0, [$page->id]);
                RenumberPages::run($others);
                RenumberPages::run(array_values(array_filter($leaving, static fn (int $id): bool => $id !== $page->id)));

                if ($to != $from) {
                    RewritePagePaths::run($page, $to);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Another request stored a page at the address after the check, or a page under this one lands on an
            // address made outside the tree: the unique index of cms_routes is the last guard, and the only one this
            // transaction writes. Caught outside it, so it has been rolled back.
            throw PagePathTaken::at($to);
        }

        $page->unsetRelation('parent');
        PageMoved::dispatch($page, $from, $to);

        return $page;
    }
}
