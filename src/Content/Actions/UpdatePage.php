<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageChanges;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\PageSlug;
use Made\CmsPackage\Content\Events\PageUpdated;
use Made\CmsPackage\Content\Exceptions\CircularPageMove;
use Made\CmsPackage\Content\Exceptions\HomePageProtected;
use Made\CmsPackage\Content\Exceptions\InvalidPageSlug;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
use Made\CmsPackage\Content\Exceptions\PagePathTooLong;
use Made\CmsPackage\Content\Exceptions\PageSlugTooLong;
use Made\CmsPackage\Content\Exceptions\ParentPageDeleted;
use Made\CmsPackage\Content\Exceptions\ParentPageNotFound;
use Made\CmsPackage\Content\Exceptions\PublicationMomentPassed;
use Made\CmsPackage\Content\Exceptions\UnknownPageType;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Route;
use Made\CmsPackage\Content\PageTypes\PageTypes;

final class UpdatePage
{
    use AsAction;

    public function __construct(
        private readonly PageTypes $types,
    ) {}

    /**
     * Changes the name, the address, the parent, the menu flag, whether visitors see a page, its type and its planned
     * publication (SchedulePublication, CancelScheduledPublication), all or nothing. A new parent puts the page last
     * among its new siblings; the pages under it move along and every address follows. A new type leaves the strips as
     * they are, also those it does not allow: it only decides which strip types can be added.
     *
     * @throws UnknownPageType when the type is not in the catalogue and the page does not have it already
     * @throws ParentPageNotFound
     * @throws ParentPageDeleted
     * @throws InvalidPageSlug
     * @throws PageSlugTooLong
     * @throws HomePageProtected
     * @throws CircularPageMove
     * @throws PagePathTaken
     * @throws PagePathTooLong when the address of the page or of a page under it gets too long
     * @throws PublicationMomentPassed when a new moment of the publication passed
     */
    public function handle(Page $page, PageChanges $changes): Page
    {
        $page->loadMissing('route');
        $slug = PageSlug::fromString(trim((string) $changes->slug) !== '' ? (string) $changes->slug : $changes->title);
        $home = $page->route?->path === PagePath::HOME;

        if ($home && $slug->value !== $page->slug) {
            throw HomePageProtected::rename($page);
        }

        $moving = $changes->parentId !== $page->parent_id;
        $parent = $moving ? FindParentPage::run($changes->parentId) : $page->parent;
        $from = PagePath::fromString((string) $page->route?->path);
        $to = $home ? $from : PagePath::fromString((string) $parent?->route?->path)->child($slug->value);

        $type = $changes->type === null ? $page->type : $changes->type->key;

        if ($type !== null && $type !== $page->type && ! $this->types->has($type)) {
            throw UnknownPageType::key($type);
        }

        $page->fill(['title' => trim($changes->title), 'slug' => $slug->value, 'in_menu' => $changes->inMenu]);

        if ($type !== $page->type) {
            $page->type = $type;
        }
        $changed = $moving || $page->isDirty();
        $hidden = $changes->hidden ?? $page->hidden;

        if (! $changed && $hidden === $page->hidden && $changes->schedule === null) {
            return $page;
        }

        try {
            DB::transaction(function () use ($page, $parent, $moving, $changed, $hidden, $from, $to, $changes): void {
                if ($changed) {
                    if ($moving) {
                        CheckPageParent::run($page, $parent);
                    }

                    if ($to != $from && Route::query()->atPath($to)->exists()) {
                        throw PagePathTaken::at($to);
                    }

                    if ($moving) {
                        $leaving = Page::query()->childrenOf($page->parent)->whereKeyNot($page->id)->inMenuOrder()->get()->map(static fn (Page $sibling): int => $sibling->id)->all();
                        $last = Page::query()->childrenOf($parent)->max('position');

                        $page->parent_id = $parent?->id;
                        $page->position = is_numeric($last) ? (int) $last + 1 : 0;
                        RenumberPages::run(array_values($leaving));
                    }

                    NotePageEditor::run($page, $changes->editorId);
                    $page->save();

                    if ($to != $from) {
                        RewritePagePaths::run($page, $to);
                    }
                }

                SetPageVisibility::run($page, $hidden, $changes->editorId);

                if ($changes->schedule?->at !== null) {
                    SchedulePublication::run($page, $changes->schedule->at, $changes->editorId);
                } elseif ($changes->schedule !== null) {
                    CancelScheduledPublication::run($page, $changes->editorId);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Another request stored a page at the address after the check: the unique index of cms_routes is the
            // last guard, and the only one this transaction writes. Caught outside it, so it has been rolled back.
            throw PagePathTaken::at($to);
        }

        if ($changed) {
            $page->unsetRelation('parent');
            PageUpdated::dispatch($page, $from, $to);
        }

        return $page;
    }
}
