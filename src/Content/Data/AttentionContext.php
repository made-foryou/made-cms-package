<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What the attention sources may know about the other pages of the site (MakeAttentionContext), worked out once for a
 * list of pages or for one page, so no source reads the other pages itself.
 */
final readonly class AttentionContext
{
    /**
     * @param  array<int, list<string>>  $sharedTitles  by the id of a page Google shows, the names of the other pages
     *                                                  Google shows with the same title in Google, by name
     */
    public function __construct(
        public array $sharedTitles = [],
    ) {}

    /**
     * The names of the other pages Google shows with the same title as this page, none when its title is its own.
     *
     * @return list<string>
     */
    public function sharingTitleWith(int $pageId): array
    {
        return $this->sharedTitles[$pageId] ?? [];
    }
}
