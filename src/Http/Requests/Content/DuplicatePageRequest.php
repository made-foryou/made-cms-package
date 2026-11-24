<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * Has no input; it turns a refusal of the copy into an error in the head of the screen of the page.
 */
final class DuplicatePageRequest extends LauncherRequest
{
    protected function error(string $key): string
    {
        return (string) __('made-cms-package::pages.errors.'.$key);
    }
}
