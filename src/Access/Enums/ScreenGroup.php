<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Enums;

/**
 * A group of screens in the menu. The order of the cases is the order of the groups in the menu.
 */
enum ScreenGroup: string
{
    case Site = 'site';
    case Collections = 'collections';
    case Findability = 'findability';
    case Management = 'management';

    public function label(): string
    {
        return (string) __('made-cms-package::screens.group.'.$this->value);
    }
}
