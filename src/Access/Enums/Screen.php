<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Enums;

/**
 * A screen of the CMS, one per menu item. The order of the cases is the order of the menu.
 */
enum Screen: string
{
    case Pages = 'pages';
    case News = 'news';
    case Media = 'media';
    case Messages = 'messages';
    case MetaTags = 'meta-tags';
    case AltTexts = 'alt-texts';
    case Links = 'links';
    case Redirects = 'redirects';
    case Sitemap = 'sitemap';
    case Keywords = 'keywords';
    case Editors = 'editors';
    case Forms = 'forms';
    case Backups = 'backups';
    case Log = 'log';
    case Domain = 'domain';

    public function label(): string
    {
        return (string) __('made-cms-package::screens.'.$this->value);
    }
}
