<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What the working copy of a page changes compared to its published strips, by strip id.
 *
 * Every strip appears in one list at most, so the count is the number of strips an editor changed.
 */
final readonly class DraftChanges
{
    /**
     * @param  bool  $live  whether the page is published
     * @param  list<string>  $added  strips in the draft that are not published
     * @param  list<string>  $changed  strips with other values or another visibility
     * @param  list<string>  $removed  published strips that are no longer in the draft
     * @param  list<string>  $moved  strips that only changed place among the strips both versions have
     * @param  bool  $publishable  whether publishing would change what visitors see
     * @param  bool  $discardable  whether the draft holds changes to throw away
     */
    public function __construct(
        public bool $live,
        public array $added = [],
        public array $changed = [],
        public array $removed = [],
        public array $moved = [],
        public bool $publishable = false,
        public bool $discardable = false,
    ) {}

    public function count(): int
    {
        return count($this->added) + count($this->changed) + count($this->removed) + count($this->moved);
    }
}
