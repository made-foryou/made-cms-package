<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What putting a version of a page back did to its draft.
 */
final readonly class RestoredVersion
{
    /**
     * @param  int  $number  the number of the version
     * @param  bool  $replacedDraft  whether a draft was there, which is gone now
     * @param  bool  $draft  whether the page has a draft now; false when the version is what visitors see
     */
    public function __construct(
        public int $number,
        public bool $replacedDraft,
        public bool $draft,
    ) {}
}
