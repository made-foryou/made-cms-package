<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Made\CmsPackage\Content\Actions\MakeRobotsTxt;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Enums\HitOutcome;
use Made\CmsPackage\Http\Responses\SiteFileResponse;
use Made\CmsPackage\Http\Routing\AddressHits;

/**
 * robots.txt of the site, for anyone. Every fetch is counted.
 */
final class ShowRobotsController
{
    public function __invoke(Request $request): Response
    {
        AddressHits::count($request, HitOutcome::Served, PagePath::fromString('robots.txt'));

        return SiteFileResponse::robots(MakeRobotsTxt::run());
    }
}
