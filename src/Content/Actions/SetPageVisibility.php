<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Events\PageVisibilityChanged;
use Made\CmsPackage\Content\Exceptions\HomePageProtected;
use Made\CmsPackage\Content\Models\Page;

final class SetPageVisibility
{
    use AsAction;

    /**
     * Hides a page from visitors or shows it again. A hidden page keeps its address, its draft and its published
     * version, but stays out of the menu, the sitemap and Google. It holds for the site at once, outside the draft.
     *
     * @throws HomePageProtected
     */
    public function handle(Page $page, bool $hidden, ?int $editorId = null): Page
    {
        $page->loadMissing('route');

        if ($hidden && $page->route?->path === PagePath::HOME) {
            throw HomePageProtected::hide($page);
        }

        if ($page->hidden === $hidden) {
            return $page;
        }

        $page->hidden = $hidden;
        NotePageEditor::run($page, $editorId);
        $page->save();
        PageVisibilityChanged::dispatch($page);

        return $page;
    }
}
