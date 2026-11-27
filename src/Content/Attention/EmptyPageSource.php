<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Attention;

use Made\CmsPackage\Content\Actions\CheckPageEmpty;
use Made\CmsPackage\Content\Data\AttentionContext;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;

/**
 * A page that was never published and holds no strip yet.
 */
final readonly class EmptyPageSource implements AttentionSource
{
    public function items(Page $page, AttentionContext $context): array
    {
        if ($page->status !== PageStatus::Draft || ! CheckPageEmpty::run($page)) {
            return [];
        }

        return [new AttentionItem(AttentionReason::Empty, AttentionSeverity::Medium)];
    }
}
