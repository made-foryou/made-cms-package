<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests\Fixtures\PageTypes\Valid\Nested;

use Made\CmsPackage\Content\PageTypes\PageType;

final class LandingPage extends PageType
{
    public function key(): string
    {
        return 'landing';
    }
}
