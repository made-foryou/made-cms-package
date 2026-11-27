<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageMetaMeasure;
use Made\CmsPackage\Content\Enums\MetaField;
use Made\CmsPackage\Content\Enums\NotShownInGoogle;
use Made\CmsPackage\Content\Models\Page;

final class MeasurePageMeta
{
    use AsAction;

    /**
     * Measures the title Google shows of a page and its description, the one rule for "no description", "too long" and
     * "in order" that the attention points and the Meta-overzicht share. It reads the `route` of the page (the title of
     * the home page), so a list loads it first.
     */
    public function handle(Page $page): PageMetaMeasure
    {
        $title = MakeGoogleTitle::run($page);

        return new PageMetaMeasure(
            googleTitle: $title,
            title: MeasureMeta::run(MetaField::Title, $title),
            description: MeasureMeta::run(MetaField::Description, $page->meta_description),
            notShown: NotShownInGoogle::of($page->status, $page->hidden, $page->indexable),
        );
    }
}
