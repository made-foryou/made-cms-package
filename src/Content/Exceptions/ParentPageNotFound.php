<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

final class ParentPageNotFound extends RuntimeException
{
    public static function id(int $id): self
    {
        return new self("There is no page [{$id}] to put a page under.");
    }
}
