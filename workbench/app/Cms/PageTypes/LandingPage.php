<?php

declare(strict_types=1);

namespace Workbench\App\Cms\PageTypes;

use Made\CmsPackage\Content\PageTypes\PageType;

/**
 * A page for one campaign: a hero and photos, nothing else, and it starts empty.
 */
final class LandingPage extends PageType
{
    public function key(): string
    {
        return 'landing';
    }

    public function label(): string
    {
        return 'Landingspagina';
    }

    public function description(): string
    {
        return 'Voor een actie of advertentie: een hero en foto’s.';
    }

    public function strips(): array
    {
        return ['hero', 'gallery'];
    }
}
