<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Data\ShownStrip;
use Made\CmsPackage\Content\Strips\StripTypes;

final class ListShownStrips
{
    use AsAction;

    public function __construct(
        private readonly StripTypes $types,
    ) {}

    /**
     * The strips of a version of a page a visitor sees, in order, each with its type: hidden strips are left out, and
     * so are strips of a type that is no longer in the catalogue, whose data stays on the page with nothing left to
     * render it. Which version a visitor gets is for the caller to say.
     *
     * @return list<ShownStrip>
     */
    public function handle(PageStrips $strips): array
    {
        $shown = [];

        foreach ($strips->strips as $strip) {
            if ($strip->visible && $this->types->has($strip->type)) {
                $shown[] = new ShownStrip($strip, $this->types->find($strip->type));
            }
        }

        return $shown;
    }
}
