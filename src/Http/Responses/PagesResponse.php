<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Data\DeletedPage;
use Made\CmsPackage\Content\Data\DeletedPages;
use Made\CmsPackage\Content\Data\PageAttention;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Data\PagesOverview;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\PageState;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Http\Cms\Day;
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
     * @param  array<int, string>  $names  the names of the editors who changed the pages last, by id (NameEditors)
     */
    public static function make(Editor $editor, array $screens, PagesOverview $overview, array $names, DeletedPages $deleted): Response
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
                'attention' => ! $overview->attention->of($node->id)->isEmpty(),
                'note' => self::note($node, $overview->attention->of($node->id), $locale),
                'modified' => self::modified($node, $names, $locale),
                'depth' => $node->depth,
                'showUrl' => PageLinks::show($node->id),
                'editUrl' => PageLinks::edit($node->id),
            ], $overview->shown->flatten()),
            'deleted' => array_map(static fn (DeletedPage $page): array => [
                'title' => $page->title,
                'path' => $page->formerPath->value,
                'deletedAt' => $page->deletedAt->settings(['locale' => $locale])->isoFormat('D MMMM YYYY'),
                'parentDeleted' => $page->parentDeleted,
                'restoreUrl' => PageLinks::restore($page->id),
            ], $deleted->pages),
            'treeScript' => LauncherAssetController::url('pages.js'),
            'keepDays' => (int) config('made-cms-package.pages.keep_deleted_days', 30),
        ], $overview->tree);
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
     * What still needs doing on a page, in a few words: first what it asks for attention, then what the pill leaves
     * out. A draft of a published page says since when, unless it is open too long, which its point of attention
     * says with the days; a hidden page is out of the menu and the sitemap, and a draft that is hidden stays hidden
     * once it is published.
     */
    private static function note(PageNode $node, PageAttention $attention, string $locale): string
    {
        $published = $node->status === PageStatus::Published;
        $text = static fn (string $key, array $replace = []): string => (string) __('made-cms-package::pages.'.$key, $replace, $locale);
        $notes = array_map(
            static fn (AttentionItem $item): string => $text('show.attention.reasons.'.$item->reason->value.'.short', $item->details),
            $attention->items,
        );
        $stale = array_filter($attention->items, static fn (AttentionItem $item): bool => $item->reason === AttentionReason::StaleDraft) !== [];

        if (! $published) {
            $notes[] = $text($node->isEmpty ? 'notes.empty' : 'notes.unpublished');
        } elseif ($node->hasDraft && ! $stale) {
            $notes[] = $node->draftStartedAt === null
                ? $text('notes.draft_changes')
                : $text('notes.draft_since', ['day' => Day::of($node->draftStartedAt, $locale)]);
        }

        if ($node->hidden) {
            $notes[] = $text($published ? 'notes.unlisted' : 'notes.hidden');
        } elseif (! $node->inMenu) {
            $notes[] = $text('notes.out_of_menu');
        }

        return implode(', ', $notes);
    }

    /**
     * When the page changed last, with who did it when that is known: "20 okt · Menno".
     *
     * @param  array<int, string>  $names
     */
    private static function modified(PageNode $node, array $names, string $locale): string
    {
        $day = Day::of($node->updatedAt, $locale);
        $name = $node->updatedBy === null ? null : ($names[$node->updatedBy] ?? null);

        return $name === null ? $day : (string) __('made-cms-package::pages.index.modified_by', ['day' => $day, 'name' => $name], $locale);
    }
}
