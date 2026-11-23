<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\Response;
use Made\CmsPackage\Content\Actions\PublishPage;
use Made\CmsPackage\Content\Exceptions\PageAlreadyPublished;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\PublishPageRequest;
use Made\CmsPackage\Http\Responses\DraftResponse;

/**
 * Puts the draft of a page live. A strip that breaks its definition answers 422 keyed "strips.<id>.<field path>".
 */
final class PublishPageController
{
    public function __invoke(PublishPageRequest $request, Page $page): Response
    {
        try {
            PublishPage::run($page, $request->editorId());
        } catch (PageAlreadyPublished) {
            $request->reject('page', 'nothing_to_publish');
        }

        return DraftResponse::done();
    }
}
