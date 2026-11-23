<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\Response;
use Made\CmsPackage\Content\Actions\DiscardDraft;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\DiscardDraftRequest;
use Made\CmsPackage\Http\Responses\DraftResponse;

/**
 * Throws the draft of a page away; visitors keep seeing the published strips.
 */
final class DiscardDraftController
{
    public function __invoke(DiscardDraftRequest $request, Page $page): Response
    {
        DiscardDraft::run($page, $request->editorId());

        return DraftResponse::done();
    }
}
