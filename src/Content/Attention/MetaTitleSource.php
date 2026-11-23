<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Attention;

use Made\CmsPackage\Content\Actions\CheckShownInGoogle;
use Made\CmsPackage\Content\Actions\MakeGoogleTitle;
use Made\CmsPackage\Content\Actions\MeasureMeta;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Enums\MetaField;
use Made\CmsPackage\Content\Models\Page;

/**
 * A page whose title in Google, its own or the one it gets without, is longer than Google shows.
 */
final readonly class MetaTitleSource implements AttentionSource
{
    public function items(Page $page): array
    {
        if (! CheckShownInGoogle::run($page)) {
            return [];
        }

        $title = MeasureMeta::run(MetaField::Title, MakeGoogleTitle::run($page));

        if (! $title->tooLong) {
            return [];
        }

        return [new AttentionItem(AttentionReason::MetaTitleTooLong, AttentionSeverity::Medium, [
            'length' => $title->length,
            'limit' => $title->limit,
        ])];
    }
}
