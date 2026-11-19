<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Builders;

use Illuminate\Database\Eloquent\Builder;
use Made\CmsPackage\Content\Models\Sitemap;

/**
 * @extends Builder<Sitemap>
 */
final class SitemapBuilder extends Builder
{
    /**
     * The sitemaps of the route group with this prefix ("" at the root).
     */
    public function forPrefix(string $prefix): self
    {
        return $this->where('prefix', $prefix);
    }

    public function newestFirst(): self
    {
        return $this->orderByDesc('built_at')->orderByDesc('id');
    }
}
