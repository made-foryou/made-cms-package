<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests\Fixtures\PageTypes\Valid;

use Made\CmsPackage\Content\PageTypes\PageType;

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
        return 'Vaste opzet: een hero en de teamkaarten.';
    }

    public function strips(): array
    {
        return ['hero', 'cards'];
    }

    public function defaults(): array
    {
        return ['hero', 'cards'];
    }
}
