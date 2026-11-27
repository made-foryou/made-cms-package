<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Attention;

use Made\CmsPackage\Content\Actions\MeasurePageMeta;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Page;

/**
 * A page Google shows without a description of its own: Google takes a piece of the page itself.
 */
final readonly class MetaDescriptionSource implements AttentionSource
{
    public function items(Page $page): array
    {
        $measure = MeasurePageMeta::run($page);

        if (! $measure->shown() || ! $measure->description->missing) {
            return [];
        }

        return [new AttentionItem(AttentionReason::MetaDescriptionMissing, AttentionSeverity::High)];
    }
}
