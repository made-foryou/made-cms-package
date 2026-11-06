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
     * Every stored strip is in the list, so its place is the position the strip Actions take. Hidden strips stay,
     * because an editor can show them again. A strip of a type that is no longer in the catalogue stays as unknown:
     * the site cannot render it, but its content is kept and an editor can remove it.
     */
    public function handle(Page $page): EditableStrips
    {
        $strips = [];

        foreach (PageStrips::fromArray($page->draft_strips ?? $page->strips)->strips as $strip) {
            if (! $this->types->has($strip->type)) {
                $strips[] = new EditableStrip($strip->id, $strip->type, $strip->type, $strip->visible, false, 0, null);

                continue;
            }

            $type = $this->types->find($strip->type);

            $strips[] = new EditableStrip(
                $strip->id,
                $strip->type,
                $type->label(),
                $strip->visible,
                true,
                count($type->fields()),
                $type->summary($strip->values),
            );
        }

        return new EditableStrips($strips);
    }
}
