<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Events\PagePublished;
use Made\CmsPackage\Content\Exceptions\PageAlreadyPublished;
use Made\CmsPackage\Content\Models\Page;

final class PublishPage
{
    use AsAction;

    /**
     * @throws PageAlreadyPublished
     */
    public function handle(Page $page): Page
    {
        if ($page->status === PageStatus::Published) {
            throw PageAlreadyPublished::for($page);
        }

        $page->status = PageStatus::Published;
        $page->published_at = now()->toImmutable();
        $page->save();

        PagePublished::dispatch($page);

        return $page;
    }
}
