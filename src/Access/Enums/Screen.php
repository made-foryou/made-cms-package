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

    /**
     * The group the screen sits under in the menu; the screens of a group follow each other in the cases.
     */
    public function group(): ScreenGroup
    {
        return match ($this) {
            self::Pages => ScreenGroup::Site,
            self::News, self::Media, self::Messages => ScreenGroup::Collections,
            self::MetaTags, self::AltTexts, self::Links, self::Redirects, self::Sitemap, self::Keywords => ScreenGroup::Findability,
            self::Editors, self::Forms, self::Backups, self::Log, self::Domain => ScreenGroup::Management,
        };
    }

    public function label(): string
    {
        return (string) __('made-cms-package::screens.label.'.$this->value);
    }
}
