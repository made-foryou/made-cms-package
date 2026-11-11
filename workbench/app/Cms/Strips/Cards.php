<?php

declare(strict_types=1);

namespace Workbench\App\Cms\Strips;

use Made\CmsPackage\Content\Strips\Fields\Choice;
use Made\CmsPackage\Content\Strips\Fields\Heading;
use Made\CmsPackage\Content\Strips\Fields\Image;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Link;
use Made\CmsPackage\Content\Strips\Fields\LongText;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Content\Strips\StripType;

/**
 * A row of cards, each with a title, a short text, an optional link and an optional icon from the media library.
 */
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

    public function fields(): array
    {
        return [
            Heading::make('kop'),
            Choice::make('kolommen', ['2' => 'Twee naast elkaar', '3' => 'Drie naast elkaar'])->default('3'),
            Items::make('kaarten', [
                Heading::make('titel')->tag('h3')->required(),
                LongText::make('tekst'),
                Link::make('link')->label('Gaat naar'),
                Image::make('icoon')->hint('Vierkant werkt het best.'),
            ])->min(1)->max(6),
        ];
    }
}
