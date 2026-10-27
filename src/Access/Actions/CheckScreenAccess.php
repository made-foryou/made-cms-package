<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\ScreenVisit;

/**
 * Decides whether a screen of the CMS opens. Only a logged-in editor with a role that grants the screen gets in.
 */
final class CheckScreenAccess
{
    use AsAction;

    public function handle(ScreenVisit $visit): bool
    {
        if ($visit->editor === null) {
            return false;
        }

        return in_array($visit->screen, ListScreens::run($visit->editor), true);
    }
}
