<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Data\StripDescription;
use Made\CmsPackage\Content\Exceptions\StripNotFound;
use Made\CmsPackage\Content\Exceptions\UnknownStripType;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;

final class DescribeStrip
{
    use AsAction;

    public function __construct(
        private readonly StripTypes $types,
    ) {}

    /**
     * A strip of the working copy (the draft, else the published version) with the fields of its type and its
     * stored values: every field of the type in order, a missing one from its default, values of fields the type
     * no longer has left out. The values are normalized as the package stores them, so rich text that reached the
     * database another way (a seeder, an import) reaches the panel cleaned, and an item without an id gets one.
     *
     * @throws StripNotFound
     * @throws UnknownStripType when the type of the strip is no longer in the catalogue
     */
    public function handle(Page $page, string $stripId): StripDescription
    {
        $strip = PageStrips::fromArray($page->draft_strips ?? $page->strips)->find($stripId)
            ?? throw StripNotFound::id($page, $stripId);
        $type = $this->types->find($strip->type);

        $values = [];

        foreach ($type->defaults() as $key => $default) {
            $values[$key] = array_key_exists($key, $strip->values) ? $strip->values[$key] : $default;
        }

        return new StripDescription($strip->id, $strip->type, $type->label(), $strip->visible, $type->describe()['fields'], $type->normalize($values));
    }
}
