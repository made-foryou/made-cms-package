<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

final class PageSlugTooLong extends RuntimeException
{
    public static function for(string $slug, int $limit): self
    {
        return new self(sprintf('The address of a page holds at most %d characters, [%s…] has %d.', $limit, mb_substr($slug, 0, 40), mb_strlen($slug)));
    }
}
