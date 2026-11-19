<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageMetaChanges;
use Made\CmsPackage\Content\Events\PageMetaUpdated;
use Made\CmsPackage\Content\Models\Page;

final class UpdatePageMeta
{
    use AsAction;

    /**
     * Changes how Google sees a page. It holds for the site at once: the google fields are no part of the draft.
     */
    public function handle(Page $page, PageMetaChanges $changes): Page
    {
        $page->fill([
            'meta_title' => $this->oneLine($changes->title),
            'meta_description' => $this->oneLine($changes->description),
            'in_sitemap' => $changes->inSitemap,
            'indexable' => $changes->indexable,
        ]);

        if (! $page->isDirty()) {
            return $page;
        }

        $page->save();
        PageMetaUpdated::dispatch($page);

        return $page;
    }

    /**
     * A meta tag holds one line; a text without characters is no text.
     */
    private function oneLine(?string $text): ?string
    {
        $text = Str::squish((string) $text);

        return $text === '' ? null : $text;
    }
}
