<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Routing;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Made\CmsPackage\Content\Actions\RecordAddressHit;
use Made\CmsPackage\Content\Data\AddressVisit;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\VisitClues;
use Made\CmsPackage\Content\Enums\HitOutcome;
use Made\CmsPackage\Support\Site;

use function Illuminate\Support\defer;

/**
 * Counts a visit of an address the package answered, after the answer went out: the visitor never waits for it, and
 * a count that fails is reported, never an error on the page. Always, also after a 404: Laravel leaves a deferred
 * callback out after an answer from 400 on unless it is told so.
 */
final class AddressHits
{
    public static function count(Request $request, HitOutcome $outcome, PagePath $path, bool $editing = false): void
    {
        $route = $request->route();

        $visit = new AddressVisit(
            SiteRoutes::prefix($route instanceof Route ? $route : null),
            $path,
            $outcome,
            new VisitClues(
                $request->userAgent(),
                $request->headers->get('referer'),
                array_values(array_unique([$request->getHost(), Site::host()])),
            ),
            $editing,
        );

        defer(static fn (): bool => RecordAddressHit::run($visit), always: true);
    }
}
