<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return (string) __('made-cms-package::pages.status.'.$this->value);
    }
}
