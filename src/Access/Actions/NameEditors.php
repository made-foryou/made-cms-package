<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Models\Editor;

final class NameEditors
{
    use AsAction;

    /**
     * The names of these editors by their id, in one query, for a screen that says who did something ("Geüpload door
     * Sanne de Vries"). An editor that is gone is left out, so the screen says it without a name.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function handle(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $names = [];

        foreach (Editor::query()->whereKey(array_values(array_unique($ids)))->get(['id', 'name']) as $editor) {
            $names[$editor->id] = $editor->name;
        }

        return $names;
    }
}
