<?php

declare(strict_types=1);

namespace Workbench\App\Cms\Strips;

use Made\CmsPackage\Content\Strips\Fields\Button;
use Made\CmsPackage\Content\Strips\Fields\Heading;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\RichText;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Content\Strips\StripType;

/**
 * The opening strip of a page: a heading, an introduction and up to two buttons.
 */
final class Hero extends StripType
{
    public function key(): string
    {
        return 'hero';
    }

    public function description(): string
    {
        return 'Grote kop met een korte introductie en knoppen.';
    }

    public function fields(): array
    {
        return [
            Text::make('label')->hint('Korte regel boven de kop.'),
            Heading::make('kop')->required(),
            RichText::make('intro')->hint('Twee zinnen is genoeg.'),
            Items::make('knoppen', [Button::make('knop')->required()])->max(2),
        ];
    }
}
