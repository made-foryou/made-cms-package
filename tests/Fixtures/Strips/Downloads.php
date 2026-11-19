<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests\Fixtures\Strips;

use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Strips\Fields\File;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Content\Strips\StripType;

/**
 * A strip of documents to download, with a video of its own: files of the media library that are no images.
 */
final class Downloads extends StripType
{
    public function key(): string
    {
        return 'downloads';
    }

    public function fields(): array
    {
        return [
            Text::make('kop'),
            File::make('video')->kinds(MediaKind::Video),
            Items::make('bestanden', [
                Text::make('titel'),
                File::make('bestand')->kinds(MediaKind::Document)->required(),
            ]),
        ];
    }
}
