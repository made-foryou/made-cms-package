<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PrunedAddressHits;
use Made\CmsPackage\Content\Models\AddressHit;
use Made\CmsPackage\Content\Models\NotFoundPath;

final class PruneAddressHits
{
    use AsAction;

    /**
     * The fewest days it keeps: the longest the screens look back on ("14 keer bezocht in 30 dagen").
     */
    public const int MIN_DAYS = RateNotFoundPath::WINDOW_DAYS;

    public const int KEEP_DAYS = 90;

    /**
     * Removes the counts of the days before the kept days, and the dead doors no one tried since then. An ignored
     * address stays: ignoring it was a choice, and it would come back to the list at its next visit.
     *
     * @param  int|null  $days  how many days it keeps, today included; the configured keep_days when left out
     */
    public function handle(?int $days = null): PrunedAddressHits
    {
        $days = max(self::MIN_DAYS, $days ?? config()->integer('made-cms-package.address_hits.keep_days', self::KEEP_DAYS));
        $since = now()->toImmutable()->startOfDay()->subDays($days);

        return new PrunedAddressHits(
            hits: AddressHit::query()->before($since)->delete(),
            paths: NotFoundPath::query()->notIgnored()->lastSeenBefore($since)->delete(),
        );
    }
}
