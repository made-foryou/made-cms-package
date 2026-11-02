<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests\Fixtures\Strips;

enum ButtonStyle: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Primair',
            self::Secondary => 'Secundair',
        };
    }
}
