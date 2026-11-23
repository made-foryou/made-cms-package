<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests\Fixtures\PageTypes\Duplicate;

use Made\CmsPackage\Content\PageTypes\PageType;

final class Second extends PageType
{
    public function key(): string
    {
        return 'team';
    }
}
