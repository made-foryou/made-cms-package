<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Builders;

use Illuminate\Database\Eloquent\Builder;
use Made\CmsPackage\Content\Models\NotFoundPath;

/**
 * @extends Builder<NotFoundPath>
 */
final class NotFoundPathBuilder extends Builder {}
