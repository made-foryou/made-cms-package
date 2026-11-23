<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Data\PagesAttention;

/**
 * Which pages a list of pages shows. Each filter asks a question of its own, so a page can answer more than one: a
 * live page with a draft is live and has something to publish, a hidden draft is a draft and hidden.
 */
enum PageFilter: string
{
    case All = 'all';
    case Live = 'live';
    case Draft = 'draft';
    case Attention = 'attention';
    case Hidden = 'hidden';

    /**
     * Live is what the pill calls live (PageState), a draft is a page with something to publish (never published, or
     * a draft with changes), attention is what lets a page stand out in a list (ListAttentionByPage) and hidden is
     * the switch "Zichtbaar voor bezoekers" off.
     */
    public function matches(PageNode $node, PagesAttention $attention): bool
    {
        return match ($this) {
            self::All => true,
            self::Live => PageState::of($node->status, $node->hidden) === PageState::Live,
            self::Draft => $node->status === PageStatus::Draft || $node->hasDraft,
            self::Attention => ! $attention->of($node->id)->isEmpty(),
            self::Hidden => $node->hidden,
        };
    }
}
