<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Data\DeletedPage;
use Made\CmsPackage\Content\Data\DeletedPages;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Data\PageTree;
use Made\CmsPackage\Content\Enums\PageState;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Http\Cms\PageLinks;
use Made\CmsPackage\Http\Cms\Shell;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;
use Made\CmsPackage\Support\Locale;

/**
 * The pages screen (design 15.1): every page in the order of the menu, and the deleted pages that can be restored.
 */
final class PagesResponse
{
    /**
     * How deep dragging and the buttons of the tree hang a page: three levels. Only the tree holds this; "Ligt onder"
     * on the page itself and MovePage go deeper.
     */
    public const int TREE_DEPTH = 3;

    /**
     * @param  list<Screen>  $screens  the screens of the editor, in menu order (ListScreens)
     */
    public static function make(Editor $editor, array $screens, PageTree $pages, DeletedPages $deleted): Response
    {
        $locale = Locale::launcher();

        return Shell::respond('made-cms-package-launcher::cms.pages.index', $editor, $screens, Screen::Pages, [
            'screen' => Screen::Pages,
            'createUrl' => PageLinks::create(),
            'tree' => self::tree($locale),
            'rows' => array_map(static fn (PageNode $node): array => [
                'id' => $node->id,
                'parentId' => $node->parentId,
                'home' => $node->path?->isHome() ?? false,
                'moveUrl' => PageLinks::move($node->id),
                'title' => $node->title,
                'path' => $node->path?->value,
                'state' => PageState::of($node->status, $node->hidden)->value,
                'live' => $node->status === PageStatus::Published,
                'note' => self::note($node),
                'modified' => $node->updatedAt->settings(['locale' => $locale])->isoFormat('D MMM YYYY'),
                'depth' => $node->depth,
                'showUrl' => PageLinks::show($node->id),
                'editUrl' => PageLinks::edit($node->id),
            ], $pages->flatten()),
            'deleted' => array_map(static fn (DeletedPage $page): array => [
                'title' => $page->title,
                'path' => $page->formerPath->value,
                'deletedAt' => $page->deletedAt->settings(['locale' => $locale])->isoFormat('D MMMM YYYY'),
                'parentDeleted' => $page->parentDeleted,
                'restoreUrl' => PageLinks::restore($page->id),
            ], $deleted->pages),
            'treeScript' => LauncherAssetController::url('pages.js'),
            'keepDays' => (int) config('made-cms-package.pages.keep_deleted_days', 30),
        ], $pages);
    }

    /**
     * What pages.js needs to move a page, as JSON on the tree: the token for its requests, how deep it hangs a page and
     * the texts it fills in.
     *
     * @return array{csrf_token: string, depth: int, texts: array<string, string>}
     */
    private static function tree(string $locale): array
    {
        /** @var array<string, string> $texts */
        $texts = __('made-cms-package::pages.tree.texts', [], $locale);

        return [
            'csrf_token' => (string) csrf_token(),
            'depth' => self::TREE_DEPTH,
            'texts' => $texts,
        ];
    }

    /**
     * What still needs doing on a page, in a few words, and what the pill leaves out: a hidden page is out of the menu
     * and the sitemap, and a draft that is hidden stays hidden once it is published.
     */
    private static function note(PageNode $node): string
    {
        $published = $node->status === PageStatus::Published;
        $notes = [];

        if ($published) {
            $notes[] = $node->hasDraft ? 'draft_changes' : null;
        } else {
            $notes[] = $node->isEmpty ? 'empty' : 'unpublished';
        }

        if ($node->hidden) {
            $notes[] = $published ? 'unlisted' : 'hidden';
        } else {
            $notes[] = $node->inMenu ? null : 'out_of_menu';
        }

        return implode(', ', array_map(
            static fn (string $note): string => (string) __('made-cms-package::pages.notes.'.$note),
            array_values(array_filter($notes)),
        ));
    }
}
