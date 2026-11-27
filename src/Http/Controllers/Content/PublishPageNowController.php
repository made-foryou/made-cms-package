<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\RedirectResponse;
use Made\CmsPackage\Content\Actions\PublishPage;
use Made\CmsPackage\Content\Exceptions\PageAlreadyPublished;
use Made\CmsPackage\Content\Exceptions\PublicationRefused;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\PublishPageNowRequest;
use Made\CmsPackage\Http\Responses\PageRedirect;

/**
 * Puts the draft of a page with a planned publication live at once, from the screen of the page; publishing takes the
 * plan away. A strip that stops it is named in the edit shell, where its field is.
 */
final class PublishPageNowController
{
    public function __invoke(PublishPageNowRequest $request, Page $page): RedirectResponse
    {
        try {
            PublishPage::run($page, $request->editorId());
        } catch (PageAlreadyPublished) {
            $request->reject('publish', 'nothing_to_publish');
        } catch (PublicationRefused) {
            $request->reject('publish', 'publish_refused');
        }

        return PageRedirect::published($page);
    }
}
