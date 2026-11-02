<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Builders;

use Illuminate\Database\Eloquent\Builder;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Models\Route;

/**
 * @extends Builder<Route>
 */
final class RouteBuilder extends Builder
{
    public function atPath(PagePath $path): self
    {
        return $this->where('path', $path->value);
    }
}
