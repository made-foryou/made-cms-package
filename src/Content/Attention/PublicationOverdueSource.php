<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Attention;

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Page;

/**
 * A planned publication whose moment passed more than OVERDUE_MINUTES ago: made-cms:publish-scheduled runs every
 * minute and takes the plan away once it is done, so a plan that is still there means the scheduler of the host does
 * not run.
 */
final readonly class PublicationOverdueSource implements AttentionSource
{
    /** The minutes the scheduler gets before a planned publication counts as late. */
    public const int OVERDUE_MINUTES = 5;

    public function items(Page $page): array
    {
        if ($page->publish_at === null || $page->publish_at->greaterThanOrEqualTo(CarbonImmutable::now()->subMinutes(self::OVERDUE_MINUTES))) {
            return [];
        }

        return [new AttentionItem(AttentionReason::PublicationOverdue, AttentionSeverity::High)];
    }
}
