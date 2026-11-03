<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\EditableStrip;
use Made\CmsPackage\Content\Data\EditableStrips;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;

final class ListEditableStrips
{
    use AsAction;

    public function __construct(
        private readonly StripTypes $types,
    ) {}

    /**
     * The strips of the working copy an editor builds: the draft, else the published version, in order.
     *
     * Hidden strips stay in the list, because an editor can show them again. Strips of a type that is
     * no longer in the catalogue are left out: the site cannot render them, so there is nothing to choose.
     */
    public function handle(Page $page): EditableStrips
    {
        $strips = [];

        foreach (PageStrips::fromArray($page->draft_strips ?? $page->strips)->strips as $strip) {
            if (! $this->types->has($strip->type)) {
                continue;
            }

            $type = $this->types->find($strip->type);

            $strips[] = new EditableStrip($strip->id, $strip->type, $type->label(), $strip->visible, count($type->fields()));
        }

        return new EditableStrips($strips);
    }
}
