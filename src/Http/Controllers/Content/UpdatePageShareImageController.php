<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\RedirectResponse;
use Made\CmsPackage\Content\Actions\SetPageShareImage;
use Made\CmsPackage\Content\Exceptions\ShareImageRefused;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\UpdatePageShareImageRequest;
use Made\CmsPackage\Http\Responses\PageRedirect;

/**
 * Saves the image of a shared link to a page, from the card "Hoe je link gedeeld wordt" on the screen of the page.
 */
final class UpdatePageShareImageController
{
    public function __invoke(UpdatePageShareImageRequest $request, Page $page): RedirectResponse
    {
        try {
            SetPageShareImage::run($page, $request->mediaId());
        } catch (ShareImageRefused $refused) {
            $request->reject('share_image', 'share_image_'.$refused->reason->value);
        }

        return PageRedirect::shareSaved($page);
    }
}
