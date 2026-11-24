<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Enums\PageState;

/**
 * A page in short (SummarizePage): how it stands, where it lies, what is on it and who made and changed it when.
 * Only facts; the screen of the page puts them into words.
 */
final readonly class PageSummary
{
    /**
     * @param  string|null  $type  the key of its page type; null for an ordinary page
     * @param  int|null  $parentId  the page it lies under; null at the top
     * @param  string|null  $parentTitle  the name of that page
     * @param  MenuPlace|null  $menuPlace  its place in the menu of the site; null outside the menu
     * @param  int  $children  the pages right under it, drafts and hidden pages too
     * @param  EditableStrips  $strips  the strips of its working copy, in order (ListEditableStrips)
     * @param  int  $fields  the fields of those strips together; a strip whose type left the catalogue counts none
     * @param  int|null  $createdBy  the editor who created it; null when that is not known
     * @param  CarbonImmutable|null  $publishedAt  when it was published last; null for a page never published
     * @param  int|null  $updatedBy  the editor who changed it last; null when that is not known
     */
    public function __construct(
        public PageState $state,
        public ?string $type,
        public ?int $parentId,
        public ?string $parentTitle,
        public ?MenuPlace $menuPlace,
        public int $children,
        public EditableStrips $strips,
        public int $fields,
        public ?CarbonImmutable $createdAt,
        public ?int $createdBy,
        public ?CarbonImmutable $publishedAt,
        public ?CarbonImmutable $updatedAt,
        public ?int $updatedBy,
    ) {}

    /**
     * The editors it names, each once, without the unknown: the ids a screen looks up.
     *
     * @return list<int>
     */
    public function editors(): array
    {
        return array_values(array_unique(array_filter(
            [$this->createdBy, $this->updatedBy],
            static fn (?int $id): bool => $id !== null,
        )));
    }
}
