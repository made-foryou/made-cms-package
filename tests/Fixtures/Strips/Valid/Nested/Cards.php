<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests\Fixtures\Strips\Valid\Nested;

use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Content\Strips\StripType;

final class Cards extends StripType
{
    public function key(): string
    {
        return 'cards';
    }

    public function label(): string
    {
        return 'Kaarten';
    }

    public function description(): string
    {
        return 'Een rij kaarten met een titel.';
    }

    public function view(): string
    {
        return 'site.cards';
    }

    public function with(array $values): array
    {
        return ['aantal' => is_array($values['kaarten'] ?? null) ? count($values['kaarten']) : 0];
    }

    public function fields(): array
    {
        return [Items::make('kaarten', [Text::make('titel')])];
    }
}
