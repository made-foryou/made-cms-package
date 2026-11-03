<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\EditMode;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Data\RenderedStrip;
use Made\CmsPackage\Content\Data\RenderedStrips;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;

final class RenderStrips
{
    use AsAction;

    public function __construct(
        private readonly StripTypes $types,
        private readonly EditMode $mode,
    ) {}

    /**
     * The strips a visitor sees on the page: the published version, in order, with rendered values.
     * In the edit mode it is the working copy an editor is building: the draft, else the published version.
     *
     * Hidden strips are left out, and so are strips of a type that is no longer in the catalogue:
     * their data stays on the page, but there is nothing left to render them with.
     */
    public function handle(Page $page): RenderedStrips
    {
        $rendered = [];
        $stored = $this->mode->active ? ($page->draft_strips ?? $page->strips) : $page->strips;

        foreach (PageStrips::fromArray($stored)->strips as $strip) {
            if (! $strip->visible || ! $this->types->has($strip->type)) {
                continue;
            }

            $rendered[] = new RenderedStrip($strip->id, $strip->type, $this->types->find($strip->type)->render($strip->values));
        }

        return new RenderedStrips($rendered);
    }
}
