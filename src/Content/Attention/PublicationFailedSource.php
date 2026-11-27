<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Attention;

use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;

/**
 * A strip stopped the publication planned for the page (publish_failed_at): the draft did not go live and waits for
 * an editor. Once the page is live without a draft there is nothing left to publish.
 */
final readonly class PublicationFailedSource implements AttentionSource
{
    public function items(Page $page): array
    {
        if ($page->publish_failed_at === null || ($page->status === PageStatus::Published && $page->draft_strips === null)) {
            return [];
        }

        return [new AttentionItem(AttentionReason::PublicationFailed, AttentionSeverity::High)];
    }
}
