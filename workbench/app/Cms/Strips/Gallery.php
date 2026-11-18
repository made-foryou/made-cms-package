<?php

declare(strict_types=1);

namespace Workbench\App\Cms\Strips;

use Made\CmsPackage\Content\Strips\Fields\Image;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Content\Strips\StripType;

/**
 * A gallery: a list of photos from the media library, each with an optional caption. The editor fills it with several
 * photos at once from the picker.
 */
final class Gallery extends StripType
{
    public function key(): string
    {
        return 'gallery';
    }

    public function label(): string
    {
        return 'Fotogalerij';
    }

    public function description(): string
    {
        return 'Foto’s uit Media naast elkaar, elk met een bijschrift.';
    }

    public function fields(): array
    {
        return [
            Items::make('fotos', [
                Image::make('foto'),
                Text::make('bijschrift'),
            ])->label('Foto’s')->itemLabel('Foto')->max(8),
        ];
    }
}
