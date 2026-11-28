<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\GoogleRobotsRules;
use Made\CmsPackage\Content\Data\RobotsRule;
use Made\CmsPackage\Content\Enums\NotShownInGoogle;
use Made\CmsPackage\Content\Enums\RobotsRuleOrigin;
use Made\CmsPackage\Content\Enums\SitemapStanding;

function zoekenBlocked(): GoogleRobotsRules
{
    return new GoogleRobotsRules([new RobotsRule('*', '/zoeken', RobotsRuleOrigin::Config)]);
}

it('says whether a page is in the sitemap, and why not', function (?NotShownInGoogle $notShown, bool $inSitemap, ?string $address, SitemapStanding $standing): void {
    expect(SitemapStanding::of($notShown, $inSitemap, $address, zoekenBlocked()))->toBe($standing);
})->with([
    'in' => [null, true, '/teams', SitemapStanding::In],
    'a draft, whatever else' => [NotShownInGoogle::Draft, false, null, SitemapStanding::Draft],
    'hidden' => [NotShownInGoogle::Hidden, true, '/bedankt', SitemapStanding::Hidden],
    'noindex' => [NotShownInGoogle::Noindex, true, '/proefles', SitemapStanding::Noindex],
    'unticked' => [null, false, '/privacy', SitemapStanding::Excluded],
    'unticked before blocked' => [null, false, '/zoeken', SitemapStanding::Excluded],
    'without an address' => [null, true, null, SitemapStanding::NoAddress],
    'blocked by robots.txt' => [null, true, '/zoeken', SitemapStanding::Blocked],
    'under a blocked address' => [null, true, '/zoeken/teams', SitemapStanding::Blocked],
]);

it('knows which standings are in, a choice of the editor, or a deliberate choice to keep it out', function (SitemapStanding $standing, bool $in, bool $choice, bool $deliberate): void {
    expect($standing->isIn())->toBe($in)
        ->and($standing->isChoice())->toBe($choice)
        ->and($standing->isDeliberate())->toBe($deliberate);
})->with([
    [SitemapStanding::In, true, true, false],
    [SitemapStanding::Excluded, false, true, true],
    [SitemapStanding::Hidden, false, false, true],
    [SitemapStanding::Noindex, false, false, true],
    [SitemapStanding::Draft, false, false, false],
    [SitemapStanding::Blocked, false, false, false],
    [SitemapStanding::NoAddress, false, false, false],
]);
