<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * Has no input: DeleteMediaFolder refuses a folder that holds files itself, keyed by "folder".
 */
final class DeleteMediaFolderRequest extends LauncherRequest
{
    protected function error(string $key): string
    {
        return (string) __('made-cms-package::media.errors.'.$key);
    }
}
