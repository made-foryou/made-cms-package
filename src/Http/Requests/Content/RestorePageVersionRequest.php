<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * Has no input: the version comes from the address, scoped to the page.
 */
final class RestorePageVersionRequest extends LauncherRequest
{
    protected function error(string $key): string
    {
        return (string) __('made-cms-package::pages.errors.'.$key);
    }
}
