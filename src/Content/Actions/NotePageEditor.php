<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Models\Page;

final class NotePageEditor
{
    use AsAction;

    /**
     * Notes who makes the change a page is about to save, right before it saves: only when it writes, so updated_by
     * moves exactly when updated_at does and a save that changes nothing stays no write. A change without an editor
     * (an import, a seeder, a command) makes it unknown, never the name of the editor before.
     */
    public function handle(Page $page, ?int $editorId): void
    {
        if ($page->isDirty()) {
            $page->updated_by = $editorId;
        }
    }
}
