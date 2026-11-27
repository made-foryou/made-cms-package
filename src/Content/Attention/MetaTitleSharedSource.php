<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Attention;

use Made\CmsPackage\Content\Actions\CheckShownInGoogle;
use Made\CmsPackage\Content\Data\AttentionContext;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Page;

/**
 * A page Google shows under the same title as another page it shows (their own titles or the ones they get without):
 * Google can hardly tell them apart. The context knows the other pages (MakeAttentionContext); the point names them.
 */
final readonly class MetaTitleSharedSource implements AttentionSource
{
    public function items(Page $page, AttentionContext $context): array
    {
        $others = $context->sharingTitleWith($page->id);

        if ($others === [] || ! CheckShownInGoogle::run($page)) {
            return [];
        }

        return [new AttentionItem(AttentionReason::MetaTitleShared, AttentionSeverity::Medium, ['count' => count($others)], $others)];
    }
}
