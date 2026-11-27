<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Models\Page;

/**
 * Where the action of an attention point of a page leads: the one place that ties a reason to an address and to the
 * screen it needs. Content knows the reason, never the screens; an action towards a screen the editor does not open
 * shows no link, so the page never links to a 403.
 */
final class AttentionActions
{
    /**
     * A photo without an alt text leads to Media, where its alt text holds everywhere: the one photo chosen, or the
     * photos of the library without one when the page has more. More than one h1 leads to the overview Koppen of the
     * edit shell, only for an editor who chooses the level of a heading (Meta-overzicht).
     *
     * @param  list<Screen>  $screens  the screens of the editor (ListScreens)
     */
    public static function url(AttentionItem $item, Page $page, array $screens): ?string
    {
        [$url, $screen] = match ($item->reason) {
            AttentionReason::MetaDescriptionMissing, AttentionReason::MetaTitleTooLong => ['#made-cms-google', null],
            AttentionReason::PublicationOverdue => ['#made-cms-basis', null],
            AttentionReason::StaleDraft, AttentionReason::Empty, AttentionReason::PublicationFailed => [PageLinks::edit($page), Screen::Pages],
            AttentionReason::ImageWithoutAlt => [
                MediaLinks::missingAlt((int) ($item->details['count'] ?? 0), (int) ($item->details['media'] ?? 0)),
                Screen::Media,
            ],
            AttentionReason::ManyH1 => [PageLinks::headings($page), Screen::MetaTags],
        };

        return $screen === null || in_array($screen, $screens, true) ? $url : null;
    }
}
