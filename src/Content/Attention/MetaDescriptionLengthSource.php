<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Attention;

use Made\CmsPackage\Content\Actions\MeasurePageMeta;
use Made\CmsPackage\Content\Data\AttentionContext;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Page;

/**
 * A page Google shows with a description longer than Google shows: the end of it falls off.
 */
final readonly class MetaDescriptionLengthSource implements AttentionSource
{
    public function items(Page $page, AttentionContext $context): array
    {
        $measure = MeasurePageMeta::run($page);

        if (! $measure->shown() || ! $measure->description->tooLong) {
            return [];
        }

        return [new AttentionItem(AttentionReason::MetaDescriptionTooLong, AttentionSeverity::Medium, [
            'length' => $measure->description->length,
            'limit' => $measure->description->limit,
        ])];
    }
}
