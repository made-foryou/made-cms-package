<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What made-cms:publish-scheduled did at a moment: the pages it put live and the pages a strip stopped.
 */
final readonly class ScheduledPublications
{
    public function __construct(
        public int $published,
        public int $refused,
    ) {}
}
