<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests\Fixtures\Strips\Duplicate;

use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Content\Strips\StripType;

final class Second extends StripType
{
    public function key(): string
    {
        return 'hero';
    }

    public function fields(): array
    {
        return [Text::make('kop')];
    }
}
