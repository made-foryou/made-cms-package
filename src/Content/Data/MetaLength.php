<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * How a title or a description in Google measures up to what Google shows of it.
 */
final readonly class MetaLength
{
    /**
     * @param  int  $length  the characters of the text as Google gets it
     * @param  int  $limit  about how many characters Google shows
     * @param  bool  $missing  there is no text, so Google takes a piece of the page itself
     * @param  bool  $tooLong  Google cuts the text off
     * @param  bool  $nearLimit  the text fits, but above nine tenths of what Google shows (`MetaField::warnAbove()`)
     */
    public function __construct(
        public int $length,
        public int $limit,
        public bool $missing,
        public bool $tooLong,
        public bool $nearLimit,
    ) {}
}
