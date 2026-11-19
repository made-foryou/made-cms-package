<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * A place of a file on the page itself, outside its strips: stored as the field of its place in cms_media_usages.
 */
enum PagePlace: string
{
    case ShareImage = 'share_image';

    public function label(): string
    {
        return (string) __('made-cms-package::media.places.'.$this->value);
    }
}
