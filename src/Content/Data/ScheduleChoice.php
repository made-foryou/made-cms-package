<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Carbon\CarbonImmutable;

/**
 * The publication an editor plans for a page: the moment its draft goes live by itself, or null for none.
 */
final readonly class ScheduleChoice
{
    public function __construct(
        public ?CarbonImmutable $at,
    ) {}
}
