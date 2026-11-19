<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * A text a page gives Google: the title of its result and the description under it.
 */
enum MetaField: string
{
    case Title = 'title';
    case Description = 'description';

    /**
     * About how many characters Google shows before it cuts the text off.
     */
    public function limit(): int
    {
        return match ($this) {
            self::Title => 60,
            self::Description => 155,
        };
    }
}
