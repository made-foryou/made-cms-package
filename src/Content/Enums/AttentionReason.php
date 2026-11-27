<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * Why a page asks for attention. The CMS says it in words and points the way to what solves it; the reason itself is
 * the fact.
 */
enum AttentionReason: string
{
    case PublicationFailed = 'publication_failed';
    case PublicationOverdue = 'publication_overdue';
    case StaleDraft = 'stale_draft';
    case MetaDescriptionMissing = 'meta_description_missing';
    case MetaTitleTooLong = 'meta_title_too_long';
    case MetaDescriptionTooLong = 'meta_description_too_long';
    case MetaTitleShared = 'meta_title_shared';
    case ImageWithoutAlt = 'image_without_alt';
    case ManyH1 = 'many_h1';
    case Empty = 'empty';

    /**
     * Whether the reason lets a page stand out in a list of pages (the dot, the filter, the count). An empty page that
     * was never published is not: it was just made, and the list says it in its own words; its own screen points
     * it out.
     */
    public function showsInLists(): bool
    {
        return $this !== self::Empty;
    }
}
