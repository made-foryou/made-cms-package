<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\NewPage;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\PageSlug;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Events\PageCreated;
use Made\CmsPackage\Content\Exceptions\InvalidPageSlug;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
use Made\CmsPackage\Content\Exceptions\PagePathTooLong;
use Made\CmsPackage\Content\Exceptions\PageSlugTooLong;
use Made\CmsPackage\Content\Exceptions\ParentPageDeleted;
use Made\CmsPackage\Content\Exceptions\ParentPageNotFound;
use Made\CmsPackage\Content\Exceptions\UnknownPageType;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Route;
use Made\CmsPackage\Content\PageTypes\PageTypes;
use Made\CmsPackage\Content\Strips\StripTypes;

final class CreatePage
{
    use AsAction;

    public function __construct(
        private readonly PageTypes $pageTypes,
        private readonly StripTypes $stripTypes,
    ) {}

    /**
     * Creates a draft with its address, last among the pages with the same parent. A page of a type starts with the
     * strips of its type in its draft; an ordinary page starts empty.
     *
     * @throws UnknownPageType
     * @throws ParentPageNotFound
     * @throws ParentPageDeleted
     * @throws InvalidPageSlug
     * @throws PageSlugTooLong
     * @throws PagePathTooLong
     * @throws PagePathTaken
     */
    public function handle(NewPage $new): Page
    {
        $strips = $new->type === null ? [] : array_map($this->stripTypes->find(...), $this->pageTypes->find($new->type)->defaults());
        $parent = FindParentPage::run($new->parentId);
        $slug = PageSlug::fromString(trim((string) $new->slug) !== '' ? (string) $new->slug : $new->title);
        $path = PagePath::fromString((string) $parent?->route?->path)->child($slug->value);

        if (! $path->fits()) {
            throw PagePathTooLong::at($path);
        }

        try {
            $page = DB::transaction(function () use ($new, $parent, $slug, $path, $strips): Page {
                if (Route::query()->atPath($path)->exists()) {
                    throw PagePathTaken::at($path);
                }

                $last = Page::query()->childrenOf($parent)->max('position');

                $page = Page::query()->make([
                    'parent_id' => $parent?->id,
                    'title' => trim($new->title),
                    'slug' => $slug->value,
                    'type' => $new->type,
                    'position' => is_numeric($last) ? (int) $last + 1 : 0,
                    'in_menu' => $new->inMenu,
                ]);
                NotePageEditor::run($page, $new->editorId);
                $page->save();
                $page->route()->create(['path' => $path->value]);

                if ($strips !== []) {
                    ChangeDraft::run($page, static function (PageStrips $draft, Page $page) use ($strips): void {
                        $page->draft_strips = (new PageStrips(array_map(MakeStrip::run(...), $strips)))->toArray();
                    }, $new->editorId);
                }

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
