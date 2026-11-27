<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\EditMode;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Data\RenderedStrip;
use Made\CmsPackage\Content\Data\RenderedStrips;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Models\Page;

final class RenderStrips
{
    use AsAction;

    public function __construct(
        private readonly EditMode $mode,
    ) {}

    /**
     * The strips a visitor sees on the page: the published version, in order, with rendered values.
     * In the edit mode it is the working copy an editor is building: the draft, else the published version.
     *
     * Hidden strips are left out, and so are strips of a type that is no longer in the catalogue: their data stays on
     * the page, but there is nothing left to render them with (ListShownStrips).
     *
     * The images of every strip come from the media library at once (FindMedia), as their url, alt text, size and
     * focal point; an image whose file is gone or is no image renders as null.
     *
     * @throws MediaDiskNotUsable when the page shows an image and the media disk gives no url a visitor can open
     */
    public function handle(Page $page): RenderedStrips
    {
        $stored = $this->mode->active ? ($page->draft_strips ?? $page->strips) : $page->strips;
        $shown = ListShownStrips::run(PageStrips::fromArray($stored));
        $ids = [];

        foreach ($shown as $strip) {
            $ids = [...$ids, ...$strip->type->media($strip->strip->values)];
        }

        $media = FindMedia::run(array_values(array_unique($ids)));
        $rendered = [];

        foreach ($shown as $strip) {
            $rendered[] = new RenderedStrip($strip->strip->id, $strip->strip->type, $strip->type->render($strip->strip->values, $media));
        }

        return new RenderedStrips($rendered);
    }
}
