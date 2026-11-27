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

    /**
     * Above how many characters a text that fits comes near the limit: nine tenths of it, in whole characters, so the
     * screens and `cms.js` (which gets this number) never round differently.
     */
    public function warnAbove(): int
    {
        return intdiv($this->limit() * 9, 10);
    }

    /**
     * The most characters an editor may store: about twice what Google shows, so a text that is too long can still be
     * saved and shortened later, but no essay ends up in the head of a page.
     */
    public function maxLength(): int
    {
        return match ($this) {
            self::Title => 120,
            self::Description => 320,
        };
    }
}
