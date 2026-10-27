<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;

/**
 * The screens an editor opens: those of all their roles together, in menu order. Menus and dashboards are built from it.
 */
final class ListScreens
{
    use AsAction;

    /**
     * @return list<Screen>
     */
    public function handle(Editor $editor): array
    {
        $granted = $editor->roles->flatMap(fn (Role $role): array => $role->screens());

        return array_values(array_filter(
            Screen::cases(),
            fn (Screen $screen): bool => $granted->containsStrict($screen),
        ));
    }
}
