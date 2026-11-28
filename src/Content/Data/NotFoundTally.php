<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * A dead door with its visits per source in the last days RateNotFoundPath weighs (its WINDOW_DAYS), to weigh it.
 */
final readonly class NotFoundTally
{
    /**
     * @param  array<string, int>  $hitsPerSource  by the value of the source, as AddressHitBuilder::hitsPerSource() gives
     */
    public function __construct(
        public PagePath $path,
        public array $hitsPerSource,
    ) {}
}
