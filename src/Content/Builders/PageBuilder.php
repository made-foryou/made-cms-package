<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Builders;

use Illuminate\Database\Eloquent\Builder;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Support\PagePath;

/**
 * @extends Builder<Page>
 */
final class PageBuilder extends Builder
{
    public function published(): self
    {
        return $this->where('status', PageStatus::Published);
    }

    public function draft(): self
    {
        return $this->where('status', PageStatus::Draft);
    }

    /**
     * The page at a path of the site; "lid-worden", "/lid-worden" and "/lid-worden/" are the same page.
     */
    public function forPath(string $path): self
    {
        return $this->where('path', PagePath::normalise($path));
    }

    /**
     * Most recently edited pages first, for "continue where you were".
     */
    public function recentlyEdited(): self
    {
        return $this->orderByDesc('updated_at');
    }
}
