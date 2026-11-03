<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests\Fixtures\Strips;

use Made\CmsPackage\Content\Strips\Fields\Link;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Content\Strips\StripType;

/**
 * A strip type whose extra data overrides a field and tries to override the type.
 */
final class Teaser extends StripType
{
    public function key(): string
    {
        return 'teaser';
    }

    public function with(array $values): array
    {
        return ['kop' => 'Uit with', 'doel' => $values['lees_meer'], 'type' => 'overschreven'];
    }

    public function fields(): array
    {
        return [
            Text::make('kop'),
            Link::make('lees_meer'),
        ];
    }
}
