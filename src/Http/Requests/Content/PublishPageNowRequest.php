<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Illuminate\Support\Str;
use Made\CmsPackage\Http\Requests\LauncherRequest;
use Made\CmsPackage\Http\Responses\PageRedirect;

/**
 * "Toch nu publiceren" in the basics of a page with a planned publication. It has no input; a refusal goes back to
 * the basics, where the button is.
 */
final class PublishPageNowRequest extends LauncherRequest
{
    protected function getRedirectUrl(): string
    {
        return Str::before(parent::getRedirectUrl(), '#').'#'.PageRedirect::BASICS;
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::pages.errors.'.$key);
    }
}
