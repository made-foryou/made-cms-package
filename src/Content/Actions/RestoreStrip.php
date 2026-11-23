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
     * nothing left of the removal. The values are checked against the strip type like UpdateStrip does, in their
     * current form (a heading stored as text is its text with the default level). The values come from the editor, so
     * for an editor who does not choose levels every heading comes back at the level of the published strip with the
     * same id (an item by its id), else at its default level. A strip whose type left the catalogue can only come back
     * with the values the published page holds; the values that come along are ignored, its visibility is kept.
     *
     * @param  int  $position  0 places it first, the number of strips places it last
     * @param  bool  $chooseLevels  whether the editor chooses the level of a heading; Http decides who does
     *
     * @throws StripAlreadyOnPage
     * @throws InvalidStripPosition
     * @throws UnknownStripType when the type is not in the catalogue and the published page does not hold the strip
     * @throws ValidationException keyed by the path of the field
     */
    public function handle(Page $page, Strip $strip, int $position, bool $chooseLevels, ?int $editorId = null): Strip
    {
        return ChangeDraft::run($page, function (PageStrips $strips, Page $page) use ($strip, $position, $chooseLevels): Strip {
            if ($strips->find($strip->id) !== null) {
                throw StripAlreadyOnPage::id($page, $strip->id);
            }

            if ($position < 0 || $position > $strips->count()) {
                throw InvalidStripPosition::for($page, $position, $strips->count());
            }

            $strip = $this->types->has($strip->type) ? $this->checked($page, $strip, $chooseLevels) : $this->published($page, $strip);

            $page->draft_strips = $strips->insert($strip, $position)->toArray();

            StripAdded::dispatch($page, $strip, $position);

            return $strip;
        }, $editorId);
    }

    /**
     * @throws ValidationException
     */
    private function checked(Page $page, Strip $strip, bool $chooseLevels): Strip
    {
        $type = $this->types->find($strip->type);
        $values = $type->normalize($strip->values);

        if (! $chooseLevels) {
            $published = PageStrips::fromArray($page->strips)->find($strip->id);
            $stored = $published !== null && $published->type === $strip->type ? $type->normalize($published->values) : [];
            $values = $type->keepLevels($stored, $values);
        }

        return $strip->withValues(CheckStripValues::run($type, $values));
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
