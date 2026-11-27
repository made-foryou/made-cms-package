<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Made\CmsPackage\Access\Enums\Screen;
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
     * @param  list<Screen>  $screens  the screens of the editor (ListScreens)
     */
    public static function url(AttentionReason $reason, Page $page, array $screens): ?string
    {
        [$url, $screen] = match ($reason) {
            AttentionReason::MetaDescriptionMissing, AttentionReason::MetaTitleTooLong => ['#made-cms-google', null],
            AttentionReason::PublicationOverdue => ['#made-cms-basis', null],
            AttentionReason::StaleDraft, AttentionReason::Empty, AttentionReason::PublicationFailed => [PageLinks::edit($page), Screen::Pages],
        };

        return $screen === null || in_array($screen, $screens, true) ? $url : null;
    }
}
