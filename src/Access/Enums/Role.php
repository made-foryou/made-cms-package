<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Enums;

/**
 * A fixed role of an editor. The role decides which screens of the CMS the editor opens.
 */
enum Role: string
{
    case Admin = 'admin';
    case Webmaster = 'webmaster';
    case ContentManager = 'content-manager';
    case SeoSpecialist = 'seo-specialist';
    case Owner = 'owner';

    /**
     * The only place that grants screens to roles. The admin gets every screen, also the ones added later.
     *
     * @return list<Screen>
     */
    public function screens(): array
    {
        return match ($this) {
            self::Admin => Screen::cases(),
            self::Webmaster => [
                Screen::Pages, Screen::News, Screen::Media, Screen::Messages,
                Screen::Editors, Screen::Forms, Screen::Backups, Screen::Log, Screen::Domain,
            ],
            self::ContentManager => [Screen::Pages, Screen::News, Screen::Media, Screen::Messages],
            self::SeoSpecialist => [
                Screen::Pages, Screen::MetaTags, Screen::AltTexts, Screen::Links,
                Screen::Redirects, Screen::Sitemap, Screen::Keywords,
            ],
            self::Owner => [Screen::Pages, Screen::News, Screen::Messages],
        };
    }

    /**
     * The given roles once each, in the order of the cases, so stored roles always look the same.
     *
     * @param  array<Role>  $roles
     * @return list<Role>
     */
    public static function ordered(array $roles): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role): bool => in_array($role, $roles, true),
        ));
    }

    public function label(): string
    {
        return (string) __('made-cms-package::roles.label.'.$this->value);
    }

    public function description(): string
    {
        return (string) __('made-cms-package::roles.description.'.$this->value);
    }
}
