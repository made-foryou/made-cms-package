<?php

declare(strict_types=1);

namespace Workbench\App\Cms\PageTypes;

use Made\CmsPackage\Content\PageTypes\PageType;

/**
 * A page of one team: it starts with a hero and the team cards, and takes photos besides.
 */
final class TeamPage extends PageType
{
    public function key(): string
    {
        return 'team';
    }

    public function label(): string
    {
        return 'Teampagina';
    }

    public function description(): string
    {
        return 'Vaste opzet: een hero en de teamkaarten, met foto’s erbij.';
    }

    public function strips(): array
    {
        return ['hero', 'cards', 'gallery'];
    }

    public function defaults(): array
    {
        return ['hero', 'cards'];
    }
}
