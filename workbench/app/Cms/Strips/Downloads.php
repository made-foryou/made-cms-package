<?php

declare(strict_types=1);

namespace Workbench\App\Cms\Strips;

use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Strips\Fields\File;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Content\Strips\StripType;

/**
 * Documents to download: a list of pdfs from the media library, each with a link text of its own. Without one the site
 * shows the description of the file, else its name.
 */
final class Downloads extends StripType
{
    public function key(): string
    {
        return 'downloads';
    }

    public function description(): string
    {
        return 'Documenten uit Media om te downloaden, elk met een eigen linktekst.';
    }

    public function fields(): array
    {
        return [
            Text::make('kop'),
            Items::make('bestanden', [
                File::make('bestand')->kinds(MediaKind::Document),
                Text::make('titel')->label('Linktekst')->hint('Leeg: de omschrijving van het bestand.'),
            ])->itemLabel('Document'),
        ];
    }
}
