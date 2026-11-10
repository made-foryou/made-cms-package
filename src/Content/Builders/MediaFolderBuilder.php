<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Builders;

use Illuminate\Database\Eloquent\Builder;
use Made\CmsPackage\Content\Models\MediaFolder;

/**
 * @extends Builder<MediaFolder>
 */
final class MediaFolderBuilder extends Builder
{
    public function alphabetical(): self
    {
        return $this->orderBy('name');
    }
}
