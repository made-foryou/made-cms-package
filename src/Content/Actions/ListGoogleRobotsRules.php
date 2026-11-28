<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\GoogleRobotsRules;
use Made\CmsPackage\Content\Data\RobotsRule;

final class ListGoogleRobotsRules
{
    use AsAction;

    /**
     * The rules of robots.txt Googlebot follows. A rule for another robot keeps that robot away only, so it never
     * takes a page out of the sitemap.
     */
    public function handle(): GoogleRobotsRules
    {
        return new GoogleRobotsRules(array_values(array_filter(
            ListRobotsRules::run(),
            static fn (RobotsRule $rule): bool => $rule->concernsGoogle(),
        )));
    }
}
