<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\Validator;
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
     *
     * @param  array<string, mixed>  $values  the fields to change, keyed by field
     *
     * @throws StripNotFound
     * @throws UnknownStripType when the type of the strip is no longer in the catalogue
     * @throws ValidationException keyed by the path of the field
     */
    public function handle(Page $page, string $stripId, array $values): Strip
    {
        $strips = PageStrips::fromArray($page->draft_strips ?? $page->strips);
        $strip = $strips->find($stripId) ?? throw StripNotFound::id($page, $stripId);
        $type = $this->types->find($strip->type);

        $merged = $type->merge([...$type->defaults(), ...$strip->values], $values);

        /** @var array<string, mixed> $validated */
        $validated = Validator::make($merged, $type->rules(), [], $type->attributes())->validate();

        $strip = $strip->withValues([...$type->defaults(), ...$type->normalize($validated)]);

        $page->draft_strips = $strips->replace($strip)->toArray();
        $page->save();

        StripUpdated::dispatch($page, $strip);

        return $strip;
    }
}
