<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests\Fixtures\PageTypes;

use Made\CmsPackage\Content\PageTypes\PageType;

/**
 * A page type built in a test, to try one definition without a class per case.
 */
final class InlinePageType extends PageType
{
    /**
     * @param  list<string>|null  $strips
     * @param  list<string>  $defaults
     */
    public function __construct(
        private readonly string $pageKey,
        private readonly ?array $strips = null,
        private readonly array $defaults = [],
    ) {}

    public function key(): string
    {
        return $this->pageKey;
    }

    public function strips(): ?array
    {
        return $this->strips;
    }

    public function defaults(): array
    {
        return $this->defaults;
    }
}
