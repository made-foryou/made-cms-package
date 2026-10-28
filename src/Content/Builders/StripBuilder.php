<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Builders;

use Illuminate\Database\Eloquent\Builder;
use Made\CmsPackage\Content\Models\Strip;

/**
 * Reads of strips. A host reads them through their page: $page->strips.
 *
 * @extends Builder<Strip>
 */
final class StripBuilder extends Builder {}
