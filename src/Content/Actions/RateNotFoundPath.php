<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\NotFoundTally;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Enums\VisitSource;

/**
 * The one rule for how much a dead door weighs, the colour of its mark in the lists: high once Google knows the
 * address (a person came from Google, or Googlebot fetched it) or HIGH_FROM people tried it in WINDOW_DAYS days;
 * medium for any other address people try; low when only bots come, or when bots look for it (CheckBotPath).
 */
final class RateNotFoundPath
{
    use AsAction;

    /**
     * The days whose visits a tally holds.
     */
    public const int WINDOW_DAYS = 30;

    /**
     * The visits of people from which an address weighs high, also without Google.
     */
    public const int HIGH_FROM = 10;

    public function handle(NotFoundTally $tally): AttentionSeverity
    {
        if (CheckBotPath::run($tally->path)) {
            return AttentionSeverity::Low;
        }

        $hits = static fn (VisitSource $source): int => $tally->hitsPerSource[$source->value] ?? 0;
        $people = array_sum(array_map($hits, VisitSource::people()));

        if ($hits(VisitSource::Google) > 0 || $hits(VisitSource::Googlebot) > 0 || $people >= self::HIGH_FROM) {
            return AttentionSeverity::High;
        }

        return $people > 0 ? AttentionSeverity::Medium : AttentionSeverity::Low;
    }
}
