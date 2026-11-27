<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use Carbon\CarbonImmutable;
use RuntimeException;

final class PublicationMomentPassed extends RuntimeException
{
    public static function at(CarbonImmutable $moment): self
    {
        return new self("A publication cannot be planned at [{$moment->toDateTimeString()}]: that moment has passed.");
    }
}
