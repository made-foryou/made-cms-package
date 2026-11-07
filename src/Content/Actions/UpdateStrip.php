<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Events\StripUpdated;
use Made\CmsPackage\Content\Exceptions\StripNotFound;
use Made\CmsPackage\Content\Exceptions\UnknownStripType;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;

final class UpdateStrip
{
    use AsAction;

    public function __construct(
        private readonly StripTypes $types,
    ) {}

    /**
     * Changes the values of a strip in the draft after checking them against its strip type.
     *
     * The values are merged with the stored values: a field left out keeps its value, a field sent as
     * null is cleared. A list replaces the stored list, but an item with the id of a stored item keeps
     * the fields it leaves out; an item without a known id is new and gets an id. The merged values are
     * checked as a whole, so a field left out can still be refused. Values the type does not know are dropped.
     * It returns the strip as it is stored afterwards.
     *
     * @param  array<string, mixed>  $values  the fields to change, keyed by field
     *
     * @throws StripNotFound
     * @throws UnknownStripType when the type of the strip is no longer in the catalogue
     * @throws ValidationException keyed by the path of the field
     */
    public function handle(Page $page, string $stripId, array $values): Strip
    {
        $strip = ChangeDraft::run($page, function (PageStrips $strips, Page $page) use ($stripId, $values): Strip {
            $strip = $strips->find($stripId) ?? throw StripNotFound::id($page, $stripId);
            $type = $this->types->find($strip->type);

            $strip = $strip->withValues(CheckStripValues::run($type, $type->merge([...$type->defaults(), ...$strip->values], $values)));

            $page->draft_strips = $strips->replace($strip)->toArray();

            StripUpdated::dispatch($page, $strip);

            return $strip;
        });

        // A change that takes the draft back to the published page drops the draft (ChangeDraft): the strip is then
        // stored as the published page holds it, with the ids of its items, which the side panel takes over.
        return $page->draft_strips === null ? PageStrips::fromArray($page->strips)->find($stripId) ?? $strip : $strip;
    }
}
