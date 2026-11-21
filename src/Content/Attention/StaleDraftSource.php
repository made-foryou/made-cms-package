<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Attention;

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;

/**
 * A draft of a published page that is open longer than pages.stale_draft_days: visitors still see the older text. A
 * page that was never published has no older text, and a draft of which the start is not known is left alone.
 */
final readonly class StaleDraftSource implements AttentionSource
{
    public function items(Page $page): array
    {
        if ($page->status !== PageStatus::Published || $page->draft_strips === null || $page->draft_started_at === null) {
            return [];
        }

        $days = (int) floor(max(0, $page->draft_started_at->diffInDays(CarbonImmutable::now())));

        if ($days <= config()->integer('made-cms-package.pages.stale_draft_days', 5)) {
            return [];
        }

        return [new AttentionItem(AttentionReason::StaleDraft, AttentionSeverity::High, ['days' => $days])];
    }
}
