<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Attention;

use Made\CmsPackage\Content\Actions\MeasurePageMeta;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Page;

/**
 * A page whose title in Google, its own or the one it gets without, is longer than Google shows.
 */
final readonly class MetaTitleSource implements AttentionSource
{
    public function items(Page $page): array
    {
        $measure = MeasurePageMeta::run($page);

        if (! $measure->shown() || ! $measure->title->tooLong) {
            return [];
        }

        return [new AttentionItem(AttentionReason::MetaTitleTooLong, AttentionSeverity::Medium, [
            'length' => $measure->title->length,
            'limit' => $measure->title->limit,
        ])];
    }
}
