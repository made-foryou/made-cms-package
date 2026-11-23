<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Models\Page;

final class CheckPageEmpty
{
    use AsAction;

    /**
     * Whether the page holds no strip, published or in its draft.
     */
    public function handle(Page $page): bool
    {
        return $page->strips === [] && ($page->draft_strips ?? []) === [];
    }
}
