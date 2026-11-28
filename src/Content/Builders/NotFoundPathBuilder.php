<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Builders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Models\NotFoundPath;
use Made\CmsPackage\Content\Models\Route;

/**
 * @extends Builder<NotFoundPath>
 */
final class NotFoundPathBuilder extends Builder
{
    public function atPath(PagePath $path): self
    {
        return $this->where('path', $path->value);
    }

    /**
     * The dead doors of the list: seen since the moment, not ignored, and without a page at the address (in any
     * status) or, later, a redirect. An address leaves it as soon as it gets one, and comes back with its history
     * when it loses it again.
     */
    public function listed(CarbonImmutable $since): self
    {
        $route = new Route;

        return $this->notIgnored()
            ->where('last_seen_at', '>=', $since)
            ->whereNotExists(
                Route::query()
                    ->select($route->qualifyColumn('id'))
                    ->whereColumn($route->qualifyColumn('path'), $this->qualifyColumn('path'))
                    ->toBase(),
            );
    }

    public function ignored(): self
    {
        return $this->whereNotNull('ignored_at');
    }

    public function notIgnored(): self
    {
        return $this->whereNull('ignored_at');
    }

    /**
     * The addresses first seen at or after the moment, for the most that may come in a day.
     */
    public function firstSeenSince(CarbonImmutable $moment): self
    {
        return $this->where('first_seen_at', '>=', $moment);
    }

    /**
     * The addresses no one asked for since the moment.
     */
    public function lastSeenBefore(CarbonImmutable $moment): self
    {
        return $this->where('last_seen_at', '<', $moment);
    }
}
