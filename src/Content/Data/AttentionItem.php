<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;

/**
 * One thing a page asks for: why, how much it weighs, and what the words about it need ("6 dagen", "68 tekens", the
 * strips it is about).
 */
final readonly class AttentionItem
{
    /**
     * @param  array<string, int|string>  $details  the facts the words about it need, by name
     * @param  list<string>  $names  the names of what it is about, in the order of the page ("Hero", "Tekst met foto")
     */
    public function __construct(
        public AttentionReason $reason,
        public AttentionSeverity $severity,
        public array $details = [],
        public array $names = [],
    ) {}
}
