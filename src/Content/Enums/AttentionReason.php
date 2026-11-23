<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * Why a page asks for attention. The CMS says it in words and points the way to what solves it; the reason itself is
 * the fact.
 */
enum AttentionReason: string
{
    case StaleDraft = 'stale_draft';
    case MetaDescriptionMissing = 'meta_description_missing';
    case MetaTitleTooLong = 'meta_title_too_long';
    case Empty = 'empty';
}
