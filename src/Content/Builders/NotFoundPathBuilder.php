<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Builders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Models\NotFoundPath;

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
     * The addresses first seen at or after the moment, for the most that may come in a day.
     */
    public function firstSeenSince(CarbonImmutable $moment): self
    {
        return $this->where('first_seen_at', '>=', $moment);
    }
}
