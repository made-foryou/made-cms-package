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
     * Replaces the values of a strip in the draft after checking them against its strip type.
     *
     * Values the type does not know are dropped; fields left out get their default.
     *
     * @param  array<string, mixed>  $values  every value of the strip, keyed by field
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

        /** @var array<string, mixed> $validated */
        $validated = Validator::make($values, $type->rules(), [], $type->attributes())->validate();

        $strip = $strip->withValues([...$type->defaults(), ...$type->normalize($validated)]);

        $page->draft_strips = $strips->replace($strip)->toArray();
        $page->save();

        StripUpdated::dispatch($page, $strip);

        return $strip;
    }
}
