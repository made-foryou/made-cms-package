<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The page open in the Meta-overzicht: its row, its own title, what Google shows of it, a proposed description and
 * its place in the round (null when it is no point: it is edited on its own).
 */
final readonly class MetaPanel
{
    /**
     * @param  string|null  $ownTitle  the title of the page itself, null when it gets the one without
     * @param  string  $fallbackTitle  the title it gets without one of its own (MakeFallbackTitle)
     * @param  PageMeta  $meta  what the head of the page tells Google (DescribePageMeta)
     */
    public function __construct(
        public MetaRow $row,
        public ?string $ownTitle,
        public string $fallbackTitle,
        public PageMeta $meta,
        public ?DescriptionSuggestion $suggestion,
        public ?MetaRoundPlace $place,
    ) {}
}
