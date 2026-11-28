<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Builders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Enums\HitOutcome;
use Made\CmsPackage\Content\Enums\VisitSource;
use Made\CmsPackage\Content\Models\AddressHit;

/**
 * Reads the counts of the addresses: narrow them down with the scopes, then sum them with the methods that end in hits
 * or ask the last day. A day is a date in the timezone of the app.
 *
 * @extends Builder<AddressHit>
 */
final class AddressHitBuilder extends Builder
{
    /**
     * The counts of an address, in the route group with this prefix ("" at the root), or in every group without one.
     */
    public function at(PagePath $path, ?string $prefix = null): self
    {
        return $this->where('path', $path->value)
            ->when($prefix !== null, fn (self $query): self => $query->where('prefix', $prefix));
    }

    /**
     * The counts of the day of the moment and the days after it.
     */
    public function since(CarbonImmutable $day): self
    {
        return $this->where('day', '>=', $day->toDateString());
    }

    /**
     * The counts of the days before the day of the moment.
     */
    public function before(CarbonImmutable $day): self
    {
        return $this->where('day', '<', $day->toDateString());
    }

    public function outcome(HitOutcome ...$outcomes): self
    {
        return $this->whereIn('outcome', array_map(static fn (HitOutcome $outcome): string => $outcome->value, $outcomes));
    }

    public function from(VisitSource ...$sources): self
    {
        return $this->whereIn('source', array_map(static fn (VisitSource $source): string => $source->value, $sources));
    }

    public function fromPeople(): self
    {
        return $this->from(...VisitSource::people());
    }

    public function fromCrawlers(): self
    {
        return $this->from(...VisitSource::crawlers());
    }

    public function totalHits(): int
    {
        return (int) $this->clone()->reorder()->toBase()->sum('hits');
    }

    /**
     * The visits per source, by the value of the source, the most first.
     *
     * @return array<string, int>
     */
    public function hitsPerSource(): array
    {
        return $this->hitsPer('source');
    }

    /**
     * The visits per page of the site that linked here, by its address, the most first.
     *
     * @return array<string, int>
     */
    public function hitsPerReferrer(): array
    {
        return $this->clone()->where('referrer_path', '!=', '')->hitsPer('referrer_path');
    }

    /**
     * The visits per address, the most first.
     *
     * @return array<string, int>
     */
    public function hitsPerPath(): array
    {
        return $this->hitsPer('path');
    }

    /**
     * The last day with a count, or null when nothing was counted.
     */
    public function lastDay(): ?CarbonImmutable
    {
        $day = $this->clone()->reorder()->toBase()->max('day');

        return is_string($day) ? CarbonImmutable::parse($day)->startOfDay() : null;
    }

    /**
     * @return array<string, int>
     */
    private function hitsPer(string $column): array
    {
        $hits = [];
        $rows = $this->clone()->reorder()->toBase()
            ->select($column)
            ->selectRaw('sum(hits) as aggregate')
            ->groupBy($column)
            ->orderByDesc('aggregate')
            ->orderBy($column)
            ->get();

        foreach ($rows as $row) {
            $hits[(string) $row->{$column}] = (int) $row->aggregate;
        }

        return $hits;
    }
}
