<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\AddressVisit;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\VisitOrigin;
use Made\CmsPackage\Content\Enums\HitOutcome;
use Made\CmsPackage\Content\Enums\VisitSource;
use Made\CmsPackage\Content\Models\AddressHit;
use Made\CmsPackage\Content\Models\NotFoundPath;
use Made\CmsPackage\Content\Models\Route;

/**
 * The one place that counts a visit of an address: one more in the row of its address, day, outcome and source, and
 * for an address without a page when it was first and last seen. It keeps counts only: no ip address, user agent,
 * cookie or moment of a single visit. True when it counted the visit.
 *
 * Not counted: an editor in the edit mode or the preview, a page for a person (the views of people belong to the
 * statistics of the site), an address longer than an address can be, and a new address without a page once the most
 * new addresses of a day came in (`address_hits.new_paths_per_day`), so a bot that tries addresses never fills the
 * table. The page of the site that linked here is kept only when it is an address of the site, for the same reason.
 */
final class RecordAddressHit
{
    use AsAction;

    /**
     * The longest prefix of a route group it counts: the column holds no more, so the unique index fits MySQL.
     */
    public const int PREFIX_LENGTH = 64;

    public const int NEW_PATHS_PER_DAY = 100;

    public function handle(AddressVisit $visit): bool
    {
        if ($visit->editing || ! $visit->path->fits() || mb_strlen($visit->prefix) > self::PREFIX_LENGTH) {
            return false;
        }

        $origin = DetermineVisitSource::run($visit->clues);

        if ($visit->outcome === HitOutcome::Page && ! $origin->source->isCrawler()) {
            return false;
        }

        $now = now()->toImmutable();

        if ($visit->outcome === HitOutcome::NotFound && ! self::noteNotFound($visit->path, $now)) {
            return false;
        }

        $tally = [
            'day' => $now->toDateString(),
            'prefix' => $visit->prefix,
            'path' => $visit->path->value,
            'outcome' => $visit->outcome->value,
            'source' => $origin->source->value,
            'referrer_path' => self::referrerPath($origin, $visit->prefix),
        ];

        // The row of the day comes once, whoever is first (its unique index decides), and every visit adds one to it
        // in the database: visits at the same time never count each other away.
        AddressHit::query()->insertOrIgnore([[...$tally, 'hits' => 0]]);
        AddressHit::query()->where($tally)->increment('hits');

        return true;
    }

    /**
     * Notes when the address was seen, or nothing when it is new and the most new addresses of the day came in.
     */
    private static function noteNotFound(PagePath $path, CarbonImmutable $now): bool
    {
        if (! NotFoundPath::query()->atPath($path)->exists()
            && NotFoundPath::query()->firstSeenSince($now->startOfDay())->count() >= self::newPathsPerDay()) {
            return false;
        }

        NotFoundPath::query()->upsert(
            [['path' => $path->value, 'first_seen_at' => $now, 'last_seen_at' => $now]],
            ['path'],
            ['last_seen_at'],
        );

        return true;
    }

    private static function newPathsPerDay(): int
    {
        return max(0, config()->integer('made-cms-package.address_hits.new_paths_per_day', self::NEW_PATHS_PER_DAY));
    }

    /**
     * The address of the page of the site that linked here, within the route group of the visit, or "" for any other
     * source and for a link from what is no address of the site.
     */
    private static function referrerPath(VisitOrigin $origin, string $prefix): string
    {
        if ($origin->source !== VisitSource::Internal || $origin->referrer === null) {
            return '';
        }

        $path = self::withinGroup($origin->referrer, $prefix);

        return Route::query()->atPath($path)->exists() ? $path->value : '';
    }

    private static function withinGroup(PagePath $path, string $prefix): PagePath
    {
        $group = '/'.mb_strtolower(trim($prefix, '/'));

        if ($group === '/' || ($path->value !== $group && ! str_starts_with($path->value, $group.'/'))) {
            return $path;
        }

        return PagePath::fromString(substr($path->value, strlen($group)));
    }
}
