<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests\Fixtures\Http;

use Made\CmsPackage\Content\Models\Page;

/**
 * An invokable responder, as a site would write one.
 */
final class ShowPage
{
    public function __invoke(Page $page): string
    {
        return 'Klasse: '.$page->title;
    }
}
