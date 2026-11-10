<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests\Fixtures\Strips\Valid;

use Made\CmsPackage\Content\Strips\Fields\Button;
use Made\CmsPackage\Content\Strips\Fields\Choice;
use Made\CmsPackage\Content\Strips\Fields\Image;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Link;
use Made\CmsPackage\Content\Strips\Fields\LongText;
use Made\CmsPackage\Content\Strips\Fields\RichText;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Content\Strips\StripType;
use Made\CmsPackage\Tests\Fixtures\Strips\ButtonStyle;

final class Hero extends StripType
{
    public function key(): string
    {
        return 'hero';
    }

    public function fields(): array
    {
        return [
            Text::make('label')->hint('Korte regel boven de kop.'),
            Text::make('kop')->required()->default('Welkom'),
            LongText::make('intro'),
            RichText::make('tekst'),
            Button::make('actie'),
            Items::make('knoppen', [
                Text::make('tekst')->required(),
                Link::make('gaat_naar')->required(),
                Choice::make('stijl', ButtonStyle::class),
            ])->min(1)->max(2),
            Image::make('foto')->hint('Minimaal 1600 px breed.'),
        ];
    }
}
