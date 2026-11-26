<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Carbon\CarbonImmutable;

/**
 * One version of a page as the screen of the page lists it: when and by whom it went live, and the strips it changed
 * by the name the structure gives them (the summary of a strip, else the label of its type, else the key of a type
 * that left the catalogue).
 */
final readonly class VersionSummary
{
    /**
     * @param  int|null  $publishedBy  the editor who published it; null when that is not known
     * @param  bool  $live  whether visitors see this version now: the newest version of a published page
     * @param  bool  $first  whether the page went live for the first time with it
     * @param  list<string>  $added
     * @param  list<string>  $changed
     * @param  list<string>  $moved
     * @param  list<string>  $removed
     */
    public function __construct(
        public int $number,
        public CarbonImmutable $publishedAt,
        public ?int $publishedBy,
        public bool $live,
        public bool $first,
        public array $added = [],
        public array $changed = [],
        public array $moved = [],
        public array $removed = [],
    ) {}
}
