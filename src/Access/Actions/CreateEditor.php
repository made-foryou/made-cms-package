<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\NewEditor;
use Made\CmsPackage\Access\Events\EditorCreated;
use Made\CmsPackage\Access\Exceptions\EditorAlreadyExists;
use Made\CmsPackage\Access\Models\Editor;

final class CreateEditor
{
    use AsAction;

    public function handle(NewEditor $new): Editor
    {
        $email = mb_strtolower(trim($new->email));

        if (Editor::query()->forEmail($email)->exists()) {
            throw EditorAlreadyExists::for($email);
        }

        $editor = Editor::query()->create([
            'email' => $email,
            'name' => trim($new->name),
            'password' => $new->password,
        ]);

        EditorCreated::dispatch($editor);

        return $editor;
    }
}
