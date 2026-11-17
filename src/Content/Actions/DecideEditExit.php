<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\EditExitSituation;
use Made\CmsPackage\Content\Enums\EditExit;
use Made\CmsPackage\Content\Enums\PageStatus;

final class DecideEditExit
{
    use AsAction;

    /**
     * The site when visitors see the page once the editor has left, else the pages screen: a page visitors do not see
     * answers 404 on the site.
     */
    public function handle(EditExitSituation $situation): EditExit
    {
        $visitorsSeeIt = $situation->publishing || $situation->status === PageStatus::Published;

        return $situation->hasAddress && $visitorsSeeIt ? EditExit::Site : EditExit::Pages;
    }
}
