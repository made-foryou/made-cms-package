<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What GenerateStripTypes did with the strip types of a scan: the classes it wrote and the types it skipped, each in
 * the order of the scan.
 */
final readonly class GeneratedStripTypes
{
    /**
     * @param  list<GeneratedStripType>  $written
     * @param  list<SkippedStripType>  $skipped
     */
    public function __construct(
        public array $written,
        public array $skipped,
    ) {}
}
