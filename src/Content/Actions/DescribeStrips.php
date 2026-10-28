<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\StripDescription;
use Made\CmsPackage\Content\Data\StripLookup;
use Made\CmsPackage\Content\Data\StripType;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;
use Made\CmsPackage\Content\Models\Strip;

/**
 * Tells the edit mode what the strips it found on a page are. The page only names strip ids; the type always comes
 * from the database. A strip type without a valid definition keeps its key as label, so one broken file never hides
 * every outline.
 */
final class DescribeStrips
{
    use AsAction;

    /**
     * @return list<StripDescription> in the order of their ids
     */
    public function handle(StripLookup $lookup): array
    {
        $labels = $this->labels();

        return array_values(array_map(
            fn (Strip $strip): StripDescription => new StripDescription(
                id: $strip->id,
                type: $strip->type,
                label: $labels[$strip->type] ?? $strip->type,
            ),
            Strip::query()->forIds($lookup->ids)->get()->all(),
        ));
    }

    /**
     * @return array<string, string> the label of every strip type, by key
     */
    private function labels(): array
    {
        try {
            $types = ListStripTypes::run();
        } catch (InvalidStripType $exception) {
            report($exception);

            return [];
        }

        return array_map(fn (StripType $type): string => $type->label, $types);
    }
}
