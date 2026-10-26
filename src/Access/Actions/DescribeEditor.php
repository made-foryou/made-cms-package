<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\EditorProfile;
use Made\CmsPackage\Access\Models\Editor;

final class DescribeEditor
{
    use AsAction;

    public function handle(Editor $editor): EditorProfile
    {
        $words = preg_split('/\s+/u', trim($editor->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $firstName = $words[0] ?? null;

        return new EditorProfile(
            firstName: $firstName,
            initial: mb_strtoupper(mb_substr($firstName ?? $editor->email, 0, 1)),
        );
    }
}
