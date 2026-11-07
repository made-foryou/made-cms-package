<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Events\StripAdded;
use Made\CmsPackage\Content\Exceptions\InvalidStripPosition;
use Made\CmsPackage\Content\Exceptions\StripAlreadyOnPage;
use Made\CmsPackage\Content\Exceptions\UnknownStripType;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;

final class RestoreStrip
{
    use AsAction;

    public function __construct(
        private readonly StripTypes $types,
    ) {}

    /**
     * Puts a removed strip back in the draft with its own id, visibility and values, so comparing the draft finds
     * nothing left of the removal. The values are checked against the strip type like UpdateStrip does. A strip whose
     * type left the catalogue can only come back with the values the published page holds; the values that come along
     * are ignored, its visibility is kept.
     *
     * @param  int  $position  0 places it first, the number of strips places it last
     *
     * @throws StripAlreadyOnPage
     * @throws InvalidStripPosition
     * @throws UnknownStripType when the type is not in the catalogue and the published page does not hold the strip
     * @throws ValidationException keyed by the path of the field
     */
    public function handle(Page $page, Strip $strip, int $position): Strip
    {
        return ChangeDraft::run($page, function (PageStrips $strips, Page $page) use ($strip, $position): Strip {
            if ($strips->find($strip->id) !== null) {
                throw StripAlreadyOnPage::id($page, $strip->id);
            }

            if ($position < 0 || $position > $strips->count()) {
                throw InvalidStripPosition::for($page, $position, $strips->count());
            }

            $strip = $this->types->has($strip->type) ? $this->checked($strip) : $this->published($page, $strip);

            $page->draft_strips = $strips->insert($strip, $position)->toArray();

            StripAdded::dispatch($page, $strip, $position);

            return $strip;
        });
    }

    /**
     * @throws ValidationException
     */
    private function checked(Strip $strip): Strip
    {
        return $strip->withValues(CheckStripValues::run($this->types->find($strip->type), $strip->values));
    }

    /**
     * @throws UnknownStripType
     */
    private function published(Page $page, Strip $strip): Strip
    {
        $published = PageStrips::fromArray($page->strips)->find($strip->id);

        if ($published === null || $published->type !== $strip->type) {
            throw UnknownStripType::key($strip->type);
        }

        return $published->withVisibility($strip->visible);
    }
}
