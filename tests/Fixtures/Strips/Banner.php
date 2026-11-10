<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests\Fixtures\Strips;

use Made\CmsPackage\Content\Strips\Fields\Heading;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Content\Strips\StripType;

/**
 * A strip type with headings: one of its own with a few levels to choose from, and one in every card.
 */
final class Banner extends StripType
{
    public function key(): string
    {
        return 'banner';
    }

    public function fields(): array
    {
        return [
            Heading::make('kop')->required()->tags(['h1', 'h2', 'h3']),
            Text::make('label'),
            Items::make('kaarten', [Heading::make('titel')->tag('h3')->tags(['h2', 'h3'])]),
        ];
    }
}
