<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Attention;

use Made\CmsPackage\Content\Actions\ListWorkingCopyMedia;
use Made\CmsPackage\Content\Data\AttentionContext;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Page;

/**
 * A page whose working copy shows a photo without an alt text, by the rule of the library (MediaBuilder::withoutAlt):
 * Google and a screen reader miss what is on it. The alt text belongs to the file, so it is filled in once, in Media.
 * Its share image is left out; its own card asks for that one.
 */
final readonly class ImageWithoutAltSource implements AttentionSource
{
    public function items(Page $page, AttentionContext $context): array
    {
        $ids = ListWorkingCopyMedia::run($page, $page->usagesWithoutAlt);

        if ($ids === []) {
            return [];
        }

        return [new AttentionItem(AttentionReason::ImageWithoutAlt, AttentionSeverity::Medium, [
            'count' => count($ids),
            'media' => $ids[0],
        ])];
    }
}
