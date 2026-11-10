<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * The version of a page a place is in: the draft editors work on (draft_strips) or what visitors see (strips).
 */
enum UsageVersion: string
{
    case Draft = 'draft';
    case Published = 'published';
}
