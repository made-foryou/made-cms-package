<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\Response;
use Made\CmsPackage\Content\Actions\MakeRobotsTxt;
use Made\CmsPackage\Http\Responses\SiteFileResponse;

/**
 * robots.txt of the site, for anyone.
 */
final class ShowRobotsController
{
    public function __invoke(): Response
    {
        return SiteFileResponse::robots(MakeRobotsTxt::run());
    }
}
