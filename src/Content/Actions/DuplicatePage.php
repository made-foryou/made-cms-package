<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\PageSlug;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Events\PageDuplicated;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
use Made\CmsPackage\Content\Exceptions\PagePathTooLong;
use Made\CmsPackage\Content\Exceptions\PageSlugTooLong;
use Made\CmsPackage\Content\Exceptions\ParentPageDeleted;
use Made\CmsPackage\Content\Exceptions\ParentPageNotFound;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Route;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Support\Locale;

final class DuplicatePage
{
    use AsAction;

    public function __construct(
        private readonly StripTypes $stripTypes,
    ) {}

    /**
     * Copies a page into a new draft under the same parent, right after it: "{name} (kopie)" at the address of the
     * parent and "{slug}-kopie", numbered when that address is taken. The copy takes the type, the meta, the
     * visibility, the place in the menu and the share image of the page, and its working copy as its draft, each
     * strip under an id of its own. A strip whose type left the catalogue stays behind: nothing renders it, and the
     * catalogue no longer says which of its values are files. The pages under the page stay where they are, and the
     * copy was never published. Duplicating the home page gives an ordinary page at the top.
     *
     * It reads the page again with its row locked, so the copy holds what is stored now, and the share image cannot
     * leave the page before the copy holds it too.
     *
     * @param  int|null  $editorId  the editor who copies it; null when that is not known
     *
     * @throws ParentPageNotFound
     * @throws ParentPageDeleted
     * @throws PageSlugTooLong
     * @throws PagePathTooLong when no slug fits at the address of the parent
     * @throws PagePathTaken when another request stores a page at the address at the same moment
     */
    public function handle(Page $page, ?int $editorId = null): Page
    {
        $path = null;

        try {
            [$copy, $original] = DB::transaction(function () use ($page, $editorId, &$path): array {
                $original = Page::query()->whereKey($page->getKey())->lockForUpdate()->firstOrFail();
                $parent = FindParentPage::run($original->parent_id);
                [$slug, $path] = $this->freeAddress(PagePath::fromString((string) $parent?->route?->path), $original->slug);
                $siblings = self::siblingIds($parent);
                $place = self::placeAfter($original, $siblings);

                $copy = Page::query()->make([
                    'parent_id' => $parent?->id,
                    'title' => (string) __('made-cms-package::pages.copy.title', ['title' => $original->title], Locale::launcher()),
                    'slug' => $slug->value,
                    'type' => $original->type,
                    'position' => $place,
                    'in_menu' => $original->in_menu,
                    'hidden' => $original->hidden,
                    'meta_title' => $original->meta_title,
                    'meta_description' => $original->meta_description,
                    'in_sitemap' => $original->in_sitemap,
                    'indexable' => $original->indexable,
                    'share_image_id' => $original->share_image_id,
                ]);
                $copy->created_by = $editorId;
                NotePageEditor::run($copy, $editorId);
                $copy->save();
                $copy->route()->create(['path' => $path->value]);

                array_splice($siblings, $place, 0, [$copy->id]);
                RenumberPages::run($siblings);

                $this->copyStrips($copy, $original, $editorId);

                return [$copy->load('route'), $original];
            });
        } catch (UniqueConstraintViolationException $exception) {
            // Another request stored a page at the address after the check: the unique index of cms_routes is the
            // last guard, and the only one this transaction writes. Caught outside it, so it has been rolled back.
            throw $path === null ? $exception : PagePathTaken::at($path);
        }

        PageDuplicated::dispatch($copy, $original);

        return $copy;
    }

    /**
     * The first free address of a copy: "{slug}-kopie", then "{slug}-kopie-2" and so on. A slug that would not fit
     * loses the end of its own part, so the copy always gets its word and its number.
     *
     * @return array{PageSlug, PagePath}
     *
     * @throws PagePathTooLong
     * @throws PageSlugTooLong
     */
    private function freeAddress(PagePath $parent, string $slug): array
    {
        $word = Str::slug((string) __('made-cms-package::pages.copy.slug', [], Locale::launcher()));
        $word = $word === '' ? 'copy' : $word;
        $number = 1;

        do {
            $copy = self::fitting($parent, $slug, '-'.$word.($number > 1 ? '-'.$number : ''));
            $path = $parent->child($copy->value);
            $number++;
        } while (Route::query()->atPath($path)->exists());

        return [$copy, $path];
    }

    /**
     * The slug with its ending, its own part cut short as far as the length of a slug and of an address need.
     *
     * @throws PagePathTooLong when nothing of the slug is left
     * @throws PageSlugTooLong
     */
    private static function fitting(PagePath $parent, string $slug, string $ending): PageSlug
    {
        $over = max(
            mb_strlen($slug.$ending) - PageSlug::MAX_LENGTH,
            mb_strlen($parent->child($slug.$ending)->value) - PagePath::MAX_LENGTH,
        );
        $kept = rtrim(mb_substr($slug, 0, max(0, mb_strlen($slug) - max(0, $over))), '-');

        if ($kept === '') {
            throw PagePathTooLong::at($parent->child($slug.$ending));
        }

        return PageSlug::fromString($kept.$ending);
    }

    /**
     * The ids of the pages under a parent, in the order of the menu.
     *
     * @return list<int>
     */
    private static function siblingIds(?Page $parent): array
    {
        return array_values(Page::query()->childrenOf($parent)->inMenuOrder()->get()->map(static fn (Page $sibling): int => $sibling->id)->all());
    }

    /**
     * The place right after the original among its siblings; the siblings after it move one place further.
     *
     * @param  list<int>  $siblings
     */
    private static function placeAfter(Page $original, array $siblings): int
    {
        $at = array_search($original->id, $siblings, true);

        return $at === false ? count($siblings) : $at + 1;
    }

    /**
     * Gives the copy the working copy of the original as its draft, without the strips whose type left the
     * catalogue. The files of the draft and the share image get their places in the usage of the copy.
     */
    private function copyStrips(Page $copy, Page $original, ?int $editorId): void
    {
        $strips = array_values(array_map(
            CopyStrip::run(...),
            array_filter(
                PageStrips::fromArray($original->draft_strips ?? $original->strips)->strips,
                fn (Strip $strip): bool => $this->stripTypes->has($strip->type),
            ),
        ));

        if ($strips === []) {
            SyncMediaUsage::run($copy);

            return;
        }

        // ChangeDraft gives the files of the draft and the share image their places, as it does after every change.
        ChangeDraft::run($copy, static function (PageStrips $draft, Page $page) use ($strips): void {
            $page->draft_strips = (new PageStrips($strips))->toArray();
        }, $editorId);
    }
}
