<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * Has no input; it turns a refusal of the action into an error the bar of the edit shell shows.
 */
final class PublishPageRequest extends LauncherRequest
{
    protected function error(string $key): string
    {
        return (string) __('made-cms-package::cms.edit.errors.'.$key);
    }
}
