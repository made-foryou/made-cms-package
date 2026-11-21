<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Models\Page;

final class MakeGoogleTitle
{
    use AsAction;

    /**
     * The title Google gets for a page: its own title, else the one it gets without (MakeFallbackTitle).
     */
    public function handle(Page $page): string
    {
        return $page->meta_title ?? MakeFallbackTitle::run($page);
    }
}
